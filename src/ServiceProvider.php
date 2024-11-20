<?php

namespace ByErikas\ValkeyTaggableCache;

use ByErikas\ValkeyTaggableCache\Cache\TaggableStore;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->booting(function () {
            Cache::extend("redis", function (Application $app) {
                $prefix = str($app["config"]["cache.prefix"])->rtrim(":");
                return Cache::repository(new TaggableStore($app["redis"], $prefix, $app["config"]["cache.stores.redis"]["connection"]));
            });
        });
    }
}
