<?php

namespace ByErikas\ClassicTaggableCache\Cache;

use Illuminate\Cache\RedisTagSet as BaseTagSet;

class TagSet extends BaseTagSet
{
    public const TAGS_PREFIX = "\1tags\1";

    /**
     * Get the unique tag identifier for a given tag.
     *
     * @param  string  $name
     * @return string
     */
    public function tagId($name)
    {
        return self::TAGS_PREFIX . $name;
    }

    /**
     * Get the tag identifier key for a given tag.
     *
     * @param  string  $name
     * @return string
     */
    public function tagKey($name)
    {
        return self::TAGS_PREFIX . $name;
    }
}
