<?php

namespace ByErikas\ValkeyTaggableCache\Cache;

use Symfony\Contracts\Cache\ItemInterface;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Repository;

/**
 * @property TaggableStore $store
 */
class TaggedRepository extends Repository
{
    protected array $tags;

    /**
     *
     * @param TaggableStore $store
     * @param array $tags
     * @return void
     */
    public function __construct(TaggableStore $store, array $tags = [])
    {
        parent::__construct($store, ['store' => 'redis']);
        $this->tags = $tags;
    }

    /**
     * Store an item in the cache.
     *
     * @param  array|string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function put($key, $value, $ttl = null): bool
    {
        if (is_array($key)) {
            return $this->putMany($key, $value);
        }

        $key = $this->store->cleanKey($key);

        if ($ttl === null) {
            return $this->forever($key, $value);
        }

        $seconds = $this->getSeconds($ttl);

        if ($seconds <= 0) {
            return $this->forget($key);
        }

        $client = $this->store->client();


        $client->get(
            $key,
            function (ItemInterface $item) use ($value, $seconds) {
                $item->expiresAfter($seconds);
                $item->tag($this->tags);

                return $value;
            },
            1,
        );

        $resultItem = $client->getItem($key);

        if ($resultItem->isHit()) {
            $this->event(new KeyWritten($this->getName(), $key, $value, $seconds));
            return true;
        }

        return false;
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function forever($key, $value)
    {
        $client = $this->store->client();

        $key = $this->store->cleanKey($key);

        $client->get(
            $key,
            function (ItemInterface $item) use ($value) {
                $item->tag($this->tags);

                return $value;
            },
            1,
        );

        $resultItem = $client->getItem($key);

        if ($resultItem->isHit()) {
            $this->event(new KeyWritten($this->getName(), $key, $value));
            return true;
        }

        return false;
    }

    /**
     * Flushes the cache for the given tags
     *
     * @return void
     */
    public function flush()
    {
        return $this->store->client()->invalidateTags($this->tags);
    }
}
