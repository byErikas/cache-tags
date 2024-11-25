# Laravel Redis/Valkey Classic Taggable Cache Driver Implementation
This package restores the ability to retrieve/flush items from the cache only using a single item tag. 
This is done by adding a new driver, `taggable-redis`, that adds the extended functionality.
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
- Different tags don't equal different namespaces, and using different tags with the same key will override the said key.  E.g.:
```php
Cache::tags(["tag1"])->put("key", true);
Cache::tags(["tag2"])->put("key", "value");

Cache::tags(["tag1"])->get("key"); //Will result in "value"
Cache::tags(["tag2"])->get("key") ; //Will result in "value"
```
Make sure you don't use tags as different namespace separators, they are not. Ensure you have unique keys to prevent incorrect hits.

# Installation
The package can be installed using:
```
composer require byerikas/classic-taggable-cache
```
To use the new driver - edit your `config/cache.php` and under `stores.YOUR_STORE.driver` set the value to `taggable-redis`, and run `php artisan optimize`.
It's recommended to have a scheduled command that would prune your stale tags to clean up memory. The command is `php artisan cache:prune-stale-tags`.

## TODO:
- Research if possible to solve the tag namespace issue
- Add testing
