# Laravel Redis/Valkey Classic Taggable Cache Implementation
This is a package that restores the ability to retrieve/flush items from cache only using a single item tag.
Laravel 10+ changed how cache logic works, and removed the ability to retrieve an item using a single tag, it it's tagged with more than a single tag. E.g.:
```php
Cache::tags(["tag1", "tag2"])->put("key", true);
$result = Cache::tags(["tag1"])->get("key") //Will result in the default value (null)
$result = Cache::tags(["tag1", "tag2"])->get("key") //Will result in true
$result = Cache::tags(["tag2", "tag1"])->get("key") //Will result in the default value (null)
```

This restores the ability to retireve items only using a single tag, and without caring for the tag order. E.g:
```php
Cache::tags(["tag1", "tag2"])->put("key", true);
$result = Cache::tags(["tag1"])->get("key") //Will result in true
$result = Cache::tags(["tag2", "tag1"])->get("key") //Will result in true
$result = Cache::tags(["tag2"])->get("key") //Will result in true
```

Using `Cache::forever()` will now store items for 100 days, not forever, to allow the values to be memory managed, instead of tags.

## TODO:
- Add PECL testing
- Add package to packagist

# Installation:
In your `composer.json`, `repositories` key add this:
```json
{
    "type": "vcs",
    "url": "https://github.com/byerikas/classic-taggable-cache.git"
}
```
it should look like this:
```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/byerikas/classic-taggable-cache.git"
    }
]
```
save your changes, and use this command to install the `main` branch version:
```
composer require byerikas/classic-taggable-cache:dev-main
```
