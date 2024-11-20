# Laravel Redis/Valkey Symfony Cache Adapter Implementation
This is a basic package that implements Symfony Cache adapter for Laravel 10/11. The code is mostly an adaptation/remix
of this repository: [eldair/laravel_symfony_cache](https://github.com/eldair/laravel_symfony_cache/tree/main) and as such, a lot of credit goes to [eldair](https://github.com/eldair).

## My additions include:
- Mapped required dependencies for PHP/Laravel using Composer, added package discovery
- Added mapping for characters that are reserved for unique patterns
- Implemented `Cache::forever()` logic

## TODO:
- This implementation restores the ability to flush a key using any of its tags. This can leave trash in your Redis/Valkey store, when a key with multiple tags gets flushed, as the unflushed tag points to nowhere. Ideally, this should be taken care of.

# Installation:
In your `composer.json`, `repositories` key add this:
```json
{
    "type": "vcs",
    "url": "https://github.com/byerikas/valkey-taggable-cache.git"
}
```
it should look like this:
```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/byerikas/valkey-taggable-cache.git"
    }
]
```
save your changes, and use this command to install the `main` branch version:
```
composer require byerikas/valkey-taggable-cache:dev-main
```
