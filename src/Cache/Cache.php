<?php

namespace ByErikas\CacheTags\Cache;

use ByErikas\CacheTags\Cache\Traits\MethodOverrides;
use Illuminate\Cache\RedisTaggedCache as BaseTaggedCache;
use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Cache\Events\CacheFlushing;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisClusterConnection;
use Illuminate\Redis\Connections\PredisConnection;

class Cache extends BaseTaggedCache
{
    use MethodOverrides;

    public const DEFAULT_CACHE_TTL = 8640000;

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $connection = $this->store->connection();

        if (
            $connection instanceof PredisClusterConnection ||
            $connection instanceof PhpRedisClusterConnection
        ) {
            return $this->flushClusteredConnection();
        }

        $this->event(new CacheFlushing($this->getName()));

        $redisPrefix = match (true) {
            $connection instanceof PhpRedisConnection => $connection->client()->getOption(\Redis::OPT_PREFIX),
            $connection instanceof PredisConnection => $connection->client()->getOptions()->prefix,
        };

        $cachePrefix = $redisPrefix . $this->store->getPrefix();

        $script = <<<'LUA'
            local prefix = table.remove(ARGV, 1)

            for i, key in ipairs(KEYS) do
                redis.call('DEL', key)

                for j, arg in ipairs(ARGV) do
                    local zkey = string.gsub(key, prefix, "")
                    redis.call('ZREM', arg, zkey)
                end
            end
        LUA;

        $prefix = $this->store->getPrefix();

        foreach ($this->tags->getNames() as $name) {
            
            /** Overriden in reference to: https://github.com/laravel/framework/pull/62215 */
            $entries = (new TagSet($this->store, [$name]))->entries()
                ->map(fn(string $key) => $prefix . $key)
                ->chunk(1000);

            foreach ($entries as $keysToBeDeleted) {
                $connection->eval(
                    $script,
                    count($keysToBeDeleted),
                    ...$keysToBeDeleted,
                    ...[str_replace('-', '%-', $cachePrefix), $cachePrefix . $this->tags->tagId($name)]
                );
            }
        }

        $this->event(new CacheFlushed($this->getName()));

        return true;
    }
}
