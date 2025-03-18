<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository; 
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected CacheRepository $cache;

    protected function defineEnvironment($app) 
    {
        // Setup cache store to use redis-tags driver
        tap($app["config"], function (ConfigRepository $config) { 
            $config->set("cache.default", "redis"); 
            $config->set("cache.stores.redis", [ 
                "driver"   => "redis-tags", 
                "connection" => "default",
                "lock_connection" => "default",
            ]); 
        });
    }

    protected function getPackageProviders($app)
    {
		return ["ByErikas\CacheTags\ServiceProvider"];
	}
    
    protected function cache(): CacheRepository
    {
        return Cache::store("redis");
    }

    protected function key()
    {
        return Str::uuid();
    }
}
