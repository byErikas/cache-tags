# Laravel Symfony Cache Adapter Implemetation
This is a basic package that implements Symfony Cache adapter for Laravel 10/11. The code is mostly an adaptation/integration
of this repository: [eldair/laravel_symfony_cache](https://github.com/eldair/laravel_symfony_cache/tree/main) and as such, the credit goes to him.

My additions include:
- Basic mapping for characters that are reserved
- Logic for cache usage when calling `forever()`