<?php

namespace ByErikas\ClassicTaggableCache\Cache;

use Illuminate\Cache\RedisStore as BaseStore;

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
}
