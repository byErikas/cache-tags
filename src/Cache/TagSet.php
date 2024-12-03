<?php

namespace ByErikas\ClassicTaggableCache\Cache;

use Carbon\Carbon;
use Illuminate\Cache\RedisTagSet as BaseTagSet;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\LazyCollection;

class TagSet extends BaseTagSet
{
    public const TAG_PREFIX = "tags\0";

    /**
     * Get the unique tag identifier for a given tag.
     *
     * @param  string  $name
     * @return string
     */
    public function tagId($name)
    {
        return self::TAG_PREFIX . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function tagIds()
    {
        return array_map([$this, 'tagId'], $this->names);
    }

    /**
     * Get the tag identifier key for a given tag.
     *
     * @param  string  $name
     * @return string
     */
    public function tagKey($name)
    {
        return self::TAG_PREFIX . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function entries()
    {
        /** @disregard P1013 */
        $connection = $this->store->connection();

        $defaultCursorValue = match (true) {
            $connection instanceof PhpRedisConnection && version_compare(phpversion('redis'), '6.1.0', '>=') => null,
            default => '0',
        };

        return LazyCollection::make(function () use ($connection, $defaultCursorValue) {
            foreach ($this->tagIds() as $tagKey) {
                $cursor = $defaultCursorValue;

                do {
                    [$cursor, $entries] = $connection->zscan(
                        $this->store->getPrefix() . $tagKey,
                        $cursor,
                        ['match' =>  "*", 'count' => 1000]
                    );

                    if (! is_array($entries)) {
                        break;
                    }

                    $entries = array_unique(array_keys($entries));

                    if (count($entries) === 0) {
                        continue;
                    }

                    foreach ($entries as $entry) {
                        yield $entry;
                    }
                } while (((string) $cursor) !== $defaultCursorValue);
            }
        });
    }

    /**
     * Add a reference entry to the tag set's underlying sorted set.
     *
     * @param  string  $key
     * @param  int|null  $ttl
     * @param  string  $updateWhen
     * @return void
     */
    public function addEntry(string $key, ?int $ttl = null, $updateWhen = null)
    {
        if (is_null($ttl)) {
            $ttl = Cache::DEFAULT_CACHE_TTL;
        }

        $ttl = Carbon::now()->addSeconds($ttl)->getTimestamp();

        foreach ($this->tagIds() as $tagKey) {
            if ($updateWhen) {
                /** @disregard P1013 */
                $this->store->connection()->zadd($this->store->getPrefix() . $tagKey, $updateWhen, $ttl, $key);
            } else {
                /** @disregard P1013 */
                $this->store->connection()->zadd($this->store->getPrefix() . $tagKey, $ttl, $key);
            }
        }
    }
}
