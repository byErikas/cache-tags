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

# Installation:
The package can be installed using:
```
composer require byerikas/classic-taggable-cache
```
To use the new driver - edit your `config/cache.php` and under `stores.YOUR_STORE.driver` set the value to `taggable-redis`, and run `php artisan optimize`.

## TODO:
- Add testing
