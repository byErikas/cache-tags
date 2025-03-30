<?php

namespace ByErikas\CacheTags;

use ByErikas\CacheTags\Cache\Store;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function register()
    {
        $this->app->afterResolving("cache", static function (Factory $factory) {
            $factory->extend("redis-tags", static function (Application $app) {
                return Cache::repository(new Store($app["redis"], $app["config"]["cache.prefix"], $app["config"]["cache.stores.redis"]["connection"]));
            });
        });
    }
}
