<?php

namespace ByErikas\ClassicTaggableCache;

use ByErikas\ClassicTaggableCache\Cache\Store;
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
            Cache::extend("redis", function (Application $app) {
                $prefix = str($app["config"]["cache.prefix"])->rtrim(":");
                return Cache::repository(new Store($app["redis"], $prefix, $app["config"]["cache.stores.redis"]["connection"]));
            });
        });
    }
}
