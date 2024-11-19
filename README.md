# Laravel Redis/Valkey Symfony Cache Adapter Implementation
This is a basic package that implements Symfony Cache adapter for Laravel 10/11. The code is mostly an adaptation/remix
of this repository: [eldair/laravel_symfony_cache](https://github.com/eldair/laravel_symfony_cache/tree/main) and as such, a lot of credit goes to [eldair](https://github.com/eldair).

## My additions include:
- Basic mapping for characters that are reserved
- Logic for cache usage when calling `forever()`

## TODO:
- This implementation restores the ability to flush a key using any of its tags. This can leave trash in your Redis/Valkey store, when a key with multiple tags gets flushed, as the unflushed tag points to nowhere. Ideally, this should be taken care of.
