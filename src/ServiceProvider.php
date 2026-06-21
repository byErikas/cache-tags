<?php

namespace ByErikas\CacheTags;

use ByErikas\CacheTags\Cache\Store;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Illuminate\Contracts\Foundation\Application;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function register()
    {
        $this->app->afterResolving("cache", static function (Factory $factory) {
            $factory->extend("redis-tags", function (Application $app, array $config) {
                /** @var Factory $this */
                $connection = $config['connection'] ?? $app["config"]["cache.stores.redis"]["connection"];

                $store = new Store($app["redis"], $app["config"]["cache.prefix"], $connection);

                $store->setLockConnection($config['lock_connection'] ?? $connection);

                return $this->repository($store);
            });
        });
    }
}
