<h1 align="center">Laravel Redis/Valkey Cache Tags</h1>

<p align="center">
  <a href="https://packagist.org/packages/byerikas/cache-tags"><img src="https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FbyErikas%2Fcache-tags%2Frefs%2Fheads%2Fmain%2Fcomposer.json&query=%24.version&prefix=v&label=packagist&color=blue" alt="Latest Stable Version"></a>
  <a href="https://github.com/byErikas/cache-tags/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-93c10b" alt="License"></a>
</p>

Allows for Redis/Valkey cache flushing multiple tagged items by a single tag. 
This is done by adding a new store driver, `redis-tags`, that adds a different functionality.
Laravel 10+ changed how cache logic works, removing the ability to retrieve an item if not using the exact tags that were present when setting a cache key. E.g.:
```php
Cache::tags(["tag1", "tag2"])->put("key", true);
Cache::tags(["tag1"])->get("key"); //Will result in the default value (null)
Cache::tags(["tag1", "tag2"])->get("key"); //Will result in true
Cache::tags(["tag2", "tag1"])->get("key"); //Will result in the default value (null)
```
This changes how that works. Tags no longer have an impact on keys. Tags are used strictly for tagging, and not for creating different key namespaces. E.g:
```php
Cache::tags(["tag1", "tag2"])->put("key", true);
Cache::tags(["tag1"])->get("key"); //Will result in true
Cache::tags(["tag2", "tag1"])->get("key"); //Will result in true
Cache::get("key"); //Will result in true
```
Using `Cache::forever()` will now store items for 100 days, not forever, to allow the values to be memory managed, instead of tags.
Flushing tags - one is enough to flush the value out of the cache. This leaves some empty references in tag sets but is mitigated by the stale tag pruning command. (see [Installation](#installation))
```php
Cache::tags(["tag1", "tag2"])->put("key", true);
Cache::tags(["tag1"])->flush(); //Will flush "key"
Cache::flush(); //Will flush "key"
```

# Limitations
Different tags DON'T equal different key namespaces. Tagged and non-tagged items use the same key sequence. Ensure keys are unique - tags only tag, not alter keys.  E.g.:
```php
Cache::tags(["tag1", "tag2"])->put("key", "value1");
/** This overwrites the key above since there is a shared tag. */
Cache::tags(["tag2"])->put("key", "value2");
Cache::tags(["tag1"])->get("key"); //Will result in "value2"
Cache::get("key"); //Will result in "value2"
```

# Installation
Please read the [Limitations](#limitations) section before use as this can have breaking changes.
The package can be installed using:
```
composer require byerikas/cache-tags
```
To use the new driver - edit your `config/cache.php` and under `stores.YOUR_STORE.driver` set the value to `redis-tags`, and run `php artisan optimize`.
It's recommended to have a scheduled command that would prune your stale tags to clean up memory. The command is `php artisan cache:prune-stale-tags`, and should come with Laravel out of the box.

To prevent any memory issues use Redis/Valkey `maxmemory-policy` of `volatile-*`.
