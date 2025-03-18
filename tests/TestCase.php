<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use \Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected Repository $cache;

    protected function cache(): Repository
    {
        return Cache::store("redis");
    }

    protected function key()
    {
        return Str::uuid();
    }
}
