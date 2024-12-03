<?php

namespace ByErikas\CacheTags\Cache;

use ByErikas\CacheTags\Cache\Traits\MethodOverrides;
use Illuminate\Cache\RedisTaggedCache as BaseTaggedCache;

class Cache extends BaseTaggedCache
{
    use MethodOverrides;

    public const DEFAULT_CACHE_TTL = 8640000;
}
