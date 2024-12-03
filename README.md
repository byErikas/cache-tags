# Laravel Redis/Valkey Classic Taggable Cache Driver Implementation
This package restores the ability to retrieve/flush items from the cache only using a single item tag. 
This is done by adding a new store driver, `taggable-redis`, that adds a different functionality.
Laravel 10+ changed how cache logic works, removing the ability to retrieve an item using a single tag if it's tagged with more than one tag. E.g.:
```php
Cache::tags(["tag1", "tag2"])->put("key", true);
$result = Cache::tags(["tag1"])->get("key") //Will result in the default value (null)
$result = Cache::tags(["tag1", "tag2"])->get("key") //Will result in true
$result = Cache::tags(["tag2", "tag1"])->get("key") //Will result in the default value (null)
```
This restores the ability to retrieve items only using a single tag, and without caring for the tag order. E.g:
```php
Cache::tags(["tag1", "tag2"])->put("key", true);
$result = Cache::tags(["tag1"])->get("key") //Will result in true
$result = Cache::tags(["tag2", "tag1"])->get("key") //Will result in true
$result = Cache::tags(["tag2"])->get("key") //Will result in true
```
Using `Cache::forever()` will now store items for 100 days, not forever, to allow the values to be memory managed, instead of tags.
Flushing tags works much like retrieval, if an item has multiple tags, one is enough to flush the value out of the cache. This leaves some empty references in tag sets but is mitigated by the stale tag pruning command. (see [Installation](#installation))
```php
Cache::tags(["tag1", "tag2"])->put("key", true);
Cache::tags(["tag1"])->flush(); //Will flush "key"
```

# Limitations
Different tags DON'T equal different key namespaces. While tagged and non-tagged items are stored differently, as the tagged items get the `tagged` prefix, ensure keys are unique - tags only tag, not differ keys.  E.g.:
```php
Cache::tags(["tag1", "tag2"])->put("key", "value1");
/** This overwrites the key above since there is a shared tag. */
Cache::tags(["tag2"])->put("key", "value2");
Cache::tags(["tag1"])->get("key"); //Will result in "value2"

Cache::tags(["tag3"])->put("key", "value3");
Cache::tags(["tag1"])->get("key"); //Will result in "value3"
Cache::tags(["tag3"])->get("key") ; //Will result in "value3"
```
Make sure you don't use overlapping tags if you want to use generic keys.

# Installation
The package can be installed using:
```
composer require byerikas/classic-taggable-cache
```
To use the new driver - edit your `config/cache.php` and under `stores.YOUR_STORE.driver` set the value to `taggable-redis`, and run `php artisan optimize`.
It's recommended to have a scheduled command that would prune your stale tags to clean up memory. The command is `php artisan cache:prune-stale-tags`.

To prevent any memory issues use Redis/Valkey `maxmemory-policy` of `volatile-*`.

## TODO:
- Add testing
