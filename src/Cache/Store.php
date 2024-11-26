<?php

namespace ByErikas\ClassicTaggableCache\Cache;

use Illuminate\Cache\RedisStore as BaseStore;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class Store extends BaseStore
{
    /**
     * {@inheritDoc}
     */
    public function tags($tags)
    {
        return new Cache($this, new TagSet($this, array_map(Cache::cleanKey(...), $tags)));
    }

    /**
     * {@inheritDoc}
     */
    public function forever($key, $value)
    {
        $key = Cache::cleanKey($key);

        return (bool) $this->connection()->set($this->prefix . $key, $this->serialize($value), "EX", Cache::DEFAULT_CACHE_TTL);
    }

    /**
     * {@inheritDoc}
     */
    protected function currentTags($chunkSize = 1000)
    {
        $connection = $this->connection();

        // Connections can have a global prefix...
        $connectionPrefix = match (true) {
            $connection instanceof PhpRedisConnection => $connection->_prefix(""),
            $connection instanceof PredisConnection => $connection->getOptions()->prefix ?: "",
            default => "",
        };

        $defaultCursorValue = match (true) {
            $connection instanceof PhpRedisConnection && version_compare(phpversion("redis"), "6.1.0", ">=") => null,
            default => "0",
        };

        $prefix = $connectionPrefix . $this->getPrefix();

        return LazyCollection::make(function () use ($connection, $chunkSize, $prefix, $defaultCursorValue) {
            $cursor = $defaultCursorValue;

            do {
                [$cursor, $tagsChunk] = $connection->scan(
                    $cursor,
                    ["match" => $prefix . TagSet::TAG_PREFIX . "*", "count" => $chunkSize]
                );

                if (! is_array($tagsChunk)) {
                    break;
                }

                $tagsChunk = array_unique($tagsChunk);

                if (empty($tagsChunk)) {
                    continue;
                }

                foreach ($tagsChunk as $tag) {
                    yield $tag;
                }
            } while (((string) $cursor) !== $defaultCursorValue);
        })->map(fn(string $tagKey) => Str::match("/^" . preg_quote($prefix, "/") . TagSet::TAG_PREFIX . "(.*)$/", $tagKey));
    }
}
