<?php

namespace ByErikas\ClassicTaggableCache\Cache\Traits;

use ByErikas\ClassicTaggableCache\Cache\Cache;
use Carbon\Carbon;
use Closure;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWriteFailed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\WritingKey;

/**
 * Cache method overrides
 *
 * Moved here to not clutter the main Cache file.
 *
 */
trait MethodOverrides
{
    use CleansKeys;

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null): mixed
    {
        if (is_array($key)) {
            return $this->many($key);
        }

        if (!str($key)->startsWith(Cache::DEFAULT_KEY_PREFIX)) {
            $key = $this->itemKey(self::cleanKey($key));
        }

        $tagNames = $this->tags->getNames();

        $this->event(new RetrievingKey($this->getName(), $key, $tagNames));
        $value = $this->store->get($key);

        if (!is_null($value)) {
            $this->event(new CacheHit($this->getName(), $key, $value, $tagNames));
            return $value;
        }

        $this->event(new CacheMissed($this->getName(), $key, $tagNames));
        return value($default);
    }

    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function add($key, $value, $ttl = null)
    {
        if (!str($key)->startsWith(Cache::DEFAULT_KEY_PREFIX)) {
            $key = $this->itemKey(self::cleanKey($key));
        }

        $seconds = null;

        if ($ttl !== null) {
            $seconds = $this->getSeconds($ttl);

            if ($seconds > 0) {
                $this->tags->addEntry($key, $seconds);
            }
        }

        return parent::add($key, $value, $ttl);
    }

    /**
     * Store an item in the cache.
     *
     * @param  array|string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function put($key, $value, $ttl = null)
    {
        if (!str($key)->startsWith(Cache::DEFAULT_KEY_PREFIX)) {
            $key = $this->itemKey(self::cleanKey($key));
        }

        if (is_null($ttl)) {
            return $this->forever($key, $value, true);
        }

        $seconds = $this->getSeconds($ttl);

        if ($seconds > 0) {
            /**
             * @disregard P1013 - @var \TagSet
             */
            $this->tags->addEntry($key, $seconds);
        }

        if (is_array($key)) {
            return $this->putMany($key, $value);
        }

        if ($ttl === null) {
            return $this->forever($key, $value, true);
        }

        $seconds = $this->getSeconds($ttl);

        if ($seconds <= 0) {
            return $this->forget($key);
        }

        $this->event(new WritingKey($this->getName(), $key, $value, $seconds, $this->tags->getNames()));

        $result = $this->store->put($key, $value, $seconds);

        if ($result) {
            $this->event(new KeyWritten($this->getName(), $key, $value, $seconds), $this->tags->getNames());
        } else {
            $this->event(new KeyWriteFailed($this->getName(), $key, $value, $seconds, $this->tags->getNames()));
        }

        return $result;
    }

    /**
     * Retrieve an item from the cache by key, refreshing it in the background if it is stale.
     *
     * @template TCacheValue
     *
     * @param  string  $key
     * @param  array{ 0: \DateTimeInterface|\DateInterval|int, 1: \DateTimeInterface|\DateInterval|int }  $ttl
     * @param  (callable(): TCacheValue)  $callback
     * @param  array{ seconds?: int, owner?: string }|null  $lock
     * @return TCacheValue
     */
    public function flexible($key, $ttl, $callback, $lock = null)
    {
        $key = self::cleanKey($key);

        [
            $key => $value,
            "illuminate.cache.flexible.created.{$key}" => $created,
        ] = $this->many([$key, "illuminate.cache.flexible.created.{$key}"]);

        if (in_array(null, [$value, $created], true)) {
            return tap(value($callback), fn($value) => $this->putMany([
                $key => $value,
                "illuminate.cache.flexible.created.{$key}" => Carbon::now()->getTimestamp(),
            ], $ttl[1]));
        }

        if (($created + $this->getSeconds($ttl[0])) > Carbon::now()->getTimestamp()) {
            return $value;
        }

        $refresh = function () use ($key, $ttl, $callback, $lock, $created) {
            /** @disregard P1013 */
            $this->store->lock(
                "illuminate.cache.flexible.lock.{$key}",
                $lock['seconds'] ?? 0,
                $lock['owner'] ?? null,
            )->get(function () use ($key, $callback, $created, $ttl) {
                if ($created !== $this->get("illuminate.cache.flexible.created.{$key}")) {
                    return;
                }

                $this->putMany([
                    $key => value($callback),
                    "illuminate.cache.flexible.created.{$key}" => Carbon::now()->getTimestamp(),
                ], $ttl[1]);
            });
        };

        defer($refresh, "illuminate.cache.flexible.{$key}");

        return $value;
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function forever($key, $value, $useOriginalKey = false)
    {
        if (!str($key)->startsWith(Cache::DEFAULT_KEY_PREFIX)) {
            $key = $this->itemKey(self::cleanKey($key));
        }

        /**@disregard P1013 */
        $this->tags->addEntry($key);

        $this->event(new WritingKey($this->getName(), $key, $value, null, $this->tags->getNames()));

        $result = $this->store->forever($key, $value);

        if ($result) {
            $this->event(new KeyWritten($this->getName(), $key, $value, null, $this->tags->getNames()));
        } else {
            $this->event(new KeyWriteFailed($this->getName(), $key, $value, null, $this->tags->getNames()));
        }

        return $result;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @template TCacheValue
     *
     * @param  string  $key
     * @param  \Closure|\DateTimeInterface|\DateInterval|int|null  $ttl
     * @param  \Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember($key, $ttl, Closure $callback)
    {
        $value = $this->get($key);

        if (! is_null($value)) {
            return $value;
        }

        $value = $callback();

        $this->put($key, $value, value($ttl, $value));

        return $value;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @template TCacheValue
     *
     * @param  string  $key
     * @param  \Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function rememberForever($key, Closure $callback)
    {
        $value = $this->get($key);

        if (! is_null($value)) {
            return $value;
        }

        $this->forever($key, $value = $callback());

        return $value;
    }


    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        if (!str($key)->startsWith(Cache::DEFAULT_KEY_PREFIX)) {
            $key = $this->itemKey(self::cleanKey($key));
        }

        $this->tags->addEntry($key, updateWhen: 'NX');
        return $this->store->increment($key, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        if (!str($key)->startsWith(Cache::DEFAULT_KEY_PREFIX)) {
            $key = $this->itemKey(self::cleanKey($key));
        }

        $this->tags->addEntry($key, updateWhen: 'NX');
        return $this->store->decrement($key, $value);
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        if (!str($key)->startsWith(Cache::DEFAULT_KEY_PREFIX)) {
            $key = $this->itemKey(self::cleanKey($key));
        }

        $tags = $this->tags->getNames();
        $this->event(new ForgettingKey($this->getName(), $key));

        return tap($this->store->forget($key), function ($result) use ($key, $tags) {
            if ($result) {
                $this->event(new KeyForgotten($this->getName(), $key, $tags));
            } else {
                $this->event(new KeyForgetFailed($this->getName(), $key, $tags));
            }
        });
    }
}
