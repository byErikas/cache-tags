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
}
