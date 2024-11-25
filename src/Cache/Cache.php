<?php

namespace ByErikas\ClassicTaggableCache\Cache;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\RetrievingKey;
use Symfony\Contracts\Cache\ItemInterface;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Cache\RedisTaggedCache as BaseTaggedCache;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Carbon;
use function Illuminate\Support\defer;

class Cache extends BaseTaggedCache
{
    use InteractsWithTime;

    public const DEFAULT_CACHE_TTL = 8640000;

    protected const RESERVED_CHARACTERS_MAP = [
        ":"     => ".",
        "@"     => "\0",
        "("     => "\1.",
        ")"     => "\2",
        "{"     => "\3",
        "}"     => "\4",
        "/"     => "\5",
        "\\"    => "\6"
    ];

    /**
     * {@inheritDoc}
     */
    public function get($key, $default = null): mixed
    {
        if (is_array($key)) {
            return $this->many($key);
        }

        $this->event(new RetrievingKey($this->getName(), $key));

        $key = $this->itemKey(self::cleanKey($key));
        $value = null;

        /**
         * @disregard P1013 - @var \TagSet
         */
        $this->tags->entries()->each(function ($item) use ($key, &$value) {
            if ($item == $key) {
                $value = $this->store->get($key);
                return false;
            }
        });

        if (!is_null($value)) {
            $this->event(new CacheHit($this->getName(), $key, $value));
            return $value;
        }

        $this->event(new CacheMissed($this->getName(), $key));
        return value($default);
    }

    /**
     * {@inheritDoc}
     */
    public function add($key, $value, $ttl = null)
    {
        $key = self::cleanKey($key);

        $seconds = null;

        if ($ttl !== null) {
            $seconds = $this->getSeconds($ttl);

            if ($seconds > 0) {
                /**
                 * @disregard P1013 - @var \TagSet
                 */
                $this->tags->addEntry(
                    $this->itemKey($key),
                    $seconds
                );
            }
        }

        return TaggedCache::add($key, $value, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function put($key, $value, $ttl = null)
    {
        $key = self::cleanKey($key);

        if (is_null($ttl)) {
            return $this->forever($key, $value);
        }

        $seconds = $this->getSeconds($ttl);

        if ($seconds > 0) {
            /**
             * @disregard P1013 - @var \TagSet
             */
            $this->tags->addEntry(
                $this->itemKey($key),
                $seconds
            );
        }

        return TaggedCache::put($key, $value, $ttl);
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

    #region Helpers

    /**
     * {@inheritdoc}
     */
    protected function itemKey($key)
    {
        return ".tagged.{$key}";
    }

    /**
     * PSR-6 doesn't allow '{}()/\@:' as cache keys, replace with unique map.
     */
    public static function cleanKey(?string $key): string
    {
        return str_replace(str_split(ItemInterface::RESERVED_CHARACTERS), Cache::RESERVED_CHARACTERS_MAP, $key);
    }

    #endregion
}
