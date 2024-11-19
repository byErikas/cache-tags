<?php

namespace ByErikas\ValkeySymfonyCache\Cache;

use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Redis\Connections\Connection;
use Symfony\Contracts\Cache\ItemInterface;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Cache\TaggableStore as IlluminateTaggableStore;
use Illuminate\Cache\PhpRedisLock;
use Illuminate\Cache\RedisLock;
use Closure;

class TaggableStore extends IlluminateTaggableStore implements LockProvider
{
    use InteractsWithTime;

    protected ?string $lockConnection = null;

    protected const RESERVED_CHARACTERS_MAP = [
        "{"     => ".lcb.",
        "}"     => ".rcb.",
        "("     => ".lb.",
        ")"     => ".rb.",
        "/"     => ".fs.",
        "\\"    => ".bs.",
        "@"     => ".at.",
        ":"     => "."
    ];

    /**
     * Create a new Redis store.
     *
     * @param  \Illuminate\Contracts\Redis\Factory  $redis
     * @param  string  $prefix
     * @param  string  $connection
     * @return void
     */
    public function __construct(
        protected Redis $redis,
        protected string $prefix = '',
        protected string $connection = 'default',
    ) {}

    /**
     * PSR-6 doesn't allow '{}()/\@:' as cache keys, replace with unique map.
     */
    public function cleanKey(string $key): string
    {
        return str_replace(str_split(ItemInterface::RESERVED_CHARACTERS), TaggableStore::RESERVED_CHARACTERS_MAP, $key);
    }

    public function setConnection(?string $connection): void
    {
        $this->connection = $connection ?: 'default';
    }

    public function connection(): Connection
    {
        return $this->redis->connection($this->connection);
    }

    /**
     * Get the Redis connection instance.
     *
     * @return RedisTagAwareAdapter
     */
    public function client(): RedisTagAwareAdapter
    {
        return new RedisTagAwareAdapter($this->connection()->client(), $this->getPrefix());
    }

    /**
     *
     * @param string $key
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function get($key)
    {
        return $this->client()->getItem($this->cleanKey($key))->get();
    }

    /**
     *
     * @param array $keys
     * @return array
     * @throws InvalidArgumentException
     */
    public function many(array $keys)
    {
        $results = $this->client()->getItems(array_map($this->cleanKey(...), $keys));
        return collect(iterator_to_array($results))->map(fn(ItemInterface $item) => $item->get())->toArray();
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     * @return bool
     * @throws InvalidArgumentException
     */
    public function add($key, $value, $seconds)
    {
        return $this->put($this->cleanKey($key), $value, $seconds);
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @param ?int $seconds
     * @return bool
     * @throws InvalidArgumentException
     */
    public function put($key, $value, $seconds)
    {
        $item = $this->client()->getItem($this->cleanKey($key));

        $item->set($value);
        $item->expiresAfter($seconds !== null ? (int) max(1, $seconds) : $seconds);

        return $this->client()->save($item);
    }

    /**
     *
     * @param array $values
     * @param int $seconds
     * @return bool
     * @throws InvalidArgumentException
     */
    public function putMany(array $values, $seconds)
    {
        $manyResult = null;

        foreach ($values as $key => $value) {
            $result = $this->put($key, $value, $seconds);

            $manyResult = is_null($manyResult) ? $result : $result && $manyResult;
        }

        return $manyResult ?: false;
    }

    /**
     *
     * @param string $key
     * @return null|float|int
     * @throws InvalidArgumentException
     */
    private function getExpiration($key): null|float|int
    {
        $item = $this->client()->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $meta = $item->getMetadata();
        $expiresAt = $meta[ItemInterface::METADATA_EXPIRY] ?? 0;

        return $expiresAt !== null ? round($expiresAt - $this->currentTime()) : null;
    }

    protected function incrementOrDecrement(string $key, int $value, Closure $callback): int|bool
    {
        $key = $this->cleanKey($key);
        $currentValue = $this->get($key);

        if ($currentValue === null) {
            return false;
        }

        $newValue = $callback($currentValue, $value);

        if ($this->put($key, $newValue, $this->getExpiration($key))) {
            return $newValue;
        }

        return false;
    }

    /**
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     * @throws InvalidArgumentException
     */
    public function increment($key, $value = 1)
    {
        return $this->incrementOrDecrement($key, $value, fn($current, $value) => $current + $value);
    }

    /**
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     * @throws InvalidArgumentException
     */
    public function decrement($key, $value = 1)
    {
        return $this->incrementOrDecrement($key, $value, fn($current, $value) => $current - $value);
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     * @throws InvalidArgumentException
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, null);
    }

    /**
     *
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function forget($key)
    {
        return $this->client()->deleteItem($this->cleanKey($key));
    }

    /**
     *
     * @return bool
     */
    public function flush()
    {
        return $this->client()->clear();
    }

    /**
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Sets the tags to be used
     *
     * @param array $tags
     * @return TaggedRepository
     */
    public function tags($tags)
    {
        return new TaggedRepository($this, array_map($this->cleanKey(...), $tags));
    }

    /**
     *
     * @param string $connection
     * @return TaggableStore
     */
    public function setLockConnection(string $connection): self
    {
        $this->lockConnection = $connection;
        return $this;
    }

    /**
     *
     * @return Connection
     */
    public function lockConnection(): Connection
    {
        return $this->redis->connection($this->lockConnection ?? $this->connection);
    }

    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null)
    {
        $lockName = $this->getPrefix() . $name;

        $lockConnection = $this->lockConnection();

        /** @disregard P1009 */
        if ($lockConnection instanceof PhpRedisConnection) {
            return new PhpRedisLock($lockConnection, $lockName, $seconds, $owner);
        }

        return new RedisLock($lockConnection, $lockName, $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restoreLock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }
}
