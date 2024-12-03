<?php

namespace ByErikas\CacheTags;

use ByErikas\CacheTags\Cache\Store;
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
        $this->app->booting(function () {
            Cache::extend("redis-tags", function (Application $app) {
                return Cache::repository(new Store($app["redis"], $app["config"]["cache.prefix"], $app["config"]["cache.stores.redis"]["connection"]));
            });
        });
    }
}
