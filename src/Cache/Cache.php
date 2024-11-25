<?php

namespace ByErikas\ClassicTaggableCache\Cache;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\RetrievingKey;
use Symfony\Contracts\Cache\ItemInterface;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Cache\RedisTaggedCache as BaseTaggedCache;
use Illuminate\Cache\TaggedCache;

class Cache extends BaseTaggedCache
{
    use InteractsWithTime;

    public const DEFAULT_CACHE_TTL = 8640000;

    protected const RESERVED_CHARACTERS_MAP = [
        ":"     => ".",
        "@"     => ".at.",
        "("     => ".ob.",
        ")"     => ".cb.",
        "{"     => ".oc.",
        "}"     => ".cc.",
        "/"     => ".f.",
        "\\"    => ".b."
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

    #region Helpers

    /**
     * {@inheritdoc}
     */
    protected function itemKey($key)
    {
        return hash("sha256", $key);
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
