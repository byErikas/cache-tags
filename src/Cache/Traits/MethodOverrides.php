<?php

namespace ByErikas\CacheTags\Cache\Traits;

use Closure;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;

/**
 * Cache method overrides
 *
 * Moved here to not clutter the main Cache file.
 *
 */
trait MethodOverrides
{
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

        $tagNames = $this->tags->getNames();

        $value = $this->store->get($key);

        if (!is_null($value)) {
            $this->event(new CacheHit($key, $value, $tagNames));
            return $value;
        }

        $this->event(new CacheMissed($key, $tagNames));
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

        $result = $this->store->put($key, $value, $seconds);

        if ($result) {
            $this->event(new KeyWritten($key, $value, $seconds), $this->tags->getNames());
        }

        return $result;
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function forever($key, $value)
    {
        /**@disregard P1013 */
        $this->tags->addEntry($key);

        $result = $this->store->forever($key, $value);

        if ($result) {
            $this->event(new KeyWritten($key, $value, null, $this->tags->getNames()));
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
        $tags = $this->tags->getNames();

        return tap($this->store->forget($key), function ($result) use ($key, $tags) {
            if ($result) {
                $this->event(new KeyForgotten($key, $tags));
            }
        });
    }
}
