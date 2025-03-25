<?php

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Artisan;

it("#1 can store and retrieve an item from the cache", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->put($key, "value", 5);
    $value = $cache->get($key);

    expect($value)->toBe("value");
});

it("#2 can check if an item exists in the cache", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->put($key, "value", 5);
    expect($cache->has($key))->toBeTrue();
    expect($cache->has($key . "_missing"))->toBeFalse();
});

it("#3 can check if an item is missing from the cache", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->put($key, "value", 5);
    expect($cache->missing($key))->toBeFalse();
    expect($cache->missing($key . "_missing"))->toBeTrue();
});

it("#4 can store and retrieve multiple items from the cache", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->putMany(["{$key}_1" => "value_1", "{$key}_2" => "value_2"], 5);
    $values = $cache->many(["{$key}_1", "{$key}_2"]);
    expect($values)->toBe(["{$key}_1" => "value_1", "{$key}_2" => "value_2"]);
});

it("#5 can increment and decrement a value in the cache", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->put($key, 10, 5);
    expect($cache->get($key))->toEqual(10);

    $cache->increment($key, 5);
    expect($cache->get($key))->toEqual(15);

    $cache->decrement($key, 5);
    expect($cache->get($key))->toEqual(10);
});

it("#6 can remove an item from the cache", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->put($key, "value", 5);
    expect($cache->get($key))->toBe("value");

    $cache->forget($key);
    expect($cache->get($key))->toBeNull();
});

it("#7 can flush all items from the cache", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->putMany([$key . "_1" => "value_1", $key . "_2" => "value_2"], 5);
    $cache->flush();

    expect($cache->get($key . "_1"))->toBeNull();
    expect($cache->get($key . "_2"))->toBeNull();
});

it("#8 can handle distributed locking", function () {
    $cache = $this->cache();
    $key = $this->key();

    $lock = $cache->lock($key, 5);
    try {
        if ($lock->get()) {
            sleep(2);
            $lock->release();
        } else {
            throw new LockTimeoutException("Failed to acquire lock.");
        }
    } catch (LockTimeoutException $e) {
        $this->fail($e->getMessage());
    }

    $newLock = $cache->lock($key, 5);
    expect($newLock->get())->toBeTrue();

    $newLock->release();
});

it("#9 can store and invalidate tagged items", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["tag_1"])->put("{$key}_1", "value_1", 5);
    $cache->tags(["tag_2"])->put("{$key}_2", "value_2", 5);

    expect($cache->tags(["tag_1"])->get("{$key}_1"))->toBe("value_1");
    expect($cache->tags(["tag_2"])->get("{$key}_2"))->toBe("value_2");

    $cache->tags(["tag_1"])->flush();
    expect($cache->tags(["tag_1"])->get("{$key}_1"))->toBeNull();
    expect($cache->tags(["tag_2"])->get("{$key}_2"))->toBe("value_2");
});

it("#10 can add an item only if it doesn't exist", function () {
    $cache = $this->cache();
    $key = $this->key();

    $added = $cache->add($key, "value", 5);
    expect($added)->toBeTrue();

    $reAdded = $cache->add($key, "value_new", 5);
    expect($reAdded)->toBeFalse();
});

it("#11 can pull an item from the cache", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->put($key, "value", 5);
    $pulled = $cache->pull($key);

    expect($pulled)->toBe("value");
    expect($cache->get($key))->toBeNull();
});

it("#12 can remember and remember forever", function () {
    $cache = $this->cache();
    $key = $this->key();

    $rememberedValue = $cache->remember("{$key}_ttl", 5, fn() => "remembered_value");
    expect($rememberedValue)->toBe("remembered_value");
    expect($cache->remember("{$key}_ttl", 5, fn() => "remembered_value_2"))->toBe("remembered_value");

    $rememberedForeverValue = $cache->rememberForever("{$key}_forever", fn() => "forever_value");
    expect($rememberedForeverValue)->toBe("forever_value");
    expect($cache->rememberForever("{$key}_forever", fn() => "forever_value_2"))->toBe("forever_value");

    $cache->forget("{$key}_forever");
    expect($cache->has("{$key}_forever"))->toBeFalse();
});

it("#13 can store and invalidate an item with a single tag", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["tag_1"])->put($key, "value", 5);
    expect($cache->tags(["tag_1"])->get($key))->toBe("value");

    $cache->tags(["tag_1"])->flush();
    expect($cache->tags(["tag_1"])->get($key))->toBeNull();
});

it("#14 can store and invalidate an item with multiple tags", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["tag_1", "tag_2"])->put($key, "value", 5);
    expect($cache->tags(["tag_1"])->get($key))->toBe("value");
    expect($cache->tags(["tag_2"])->get($key))->toBe("value");

    $cache->tags(["tag_1"])->flush();
    expect($cache->tags(["tag_1"])->get($key))->toBeNull();
    expect($cache->tags(["tag_2"])->get($key))->toBeNull();
});

it("#15 can store multiple items with shared tags and invalidate them", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["shared_tag"])->putMany([
        "{$key}_1" => "value_1",
        "{$key}_2" => "value_2",
    ], 5);

    expect($cache->tags(["shared_tag"])->get("{$key}_1"))->toBe("value_1");
    expect($cache->tags(["shared_tag"])->get("{$key}_2"))->toBe("value_2");

    $cache->tags(["shared_tag"])->flush();
    expect($cache->tags(["shared_tag"])->get("{$key}_1"))->toBeNull();
    expect($cache->tags(["shared_tag"])->get("{$key}_2"))->toBeNull();
});

it("#16 can invalidate multiple tags at once", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["tag_1"])->putMany([
        "{$key}_1" => "value_1",
        "{$key}_2" => "value_2",
    ], 5);

    $cache->tags(["tag_2"])->putMany([
        "{$key}_3" => "value_3",
        "{$key}_4" => "value_4",
    ], 5);

    expect($cache->tags(["tag_1"])->many(["{$key}_1", "{$key}_2"]))->toBe([
        "{$key}_1" => "value_1",
        "{$key}_2" => "value_2",
    ]);

    expect($cache->tags(["tag_2"])->many(["{$key}_3", "{$key}_4"]))->toBe([
        "{$key}_3" => "value_3",
        "{$key}_4" => "value_4",
    ]);

    $cache->tags(["tag_1", "tag_2"])->flush();

    expect($cache->tags(["tag_1"])->many(["{$key}_1", "{$key}_2"]))->toBe([
        "{$key}_1" => null,
        "{$key}_2" => null,
    ]);

    expect($cache->tags(["tag_2"])->many(["{$key}_3", "{$key}_4"]))->toBe([
        "{$key}_3" => null,
        "{$key}_4" => null,
    ]);
});

it("#17 can handle retrieving items in different tag orders", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["tag_1", "tag_2"])->put($key, "value", 5);

    expect($cache->tags(["tag_1"])->get($key))->toBe("value");
    expect($cache->tags(["tag_2"])->get($key))->toBe("value");
    expect($cache->tags(["tag_2", "tag_1"])->get($key))->toBe("value");
    expect($cache->get($key))->toBe("value");
});

it("#18 can override keys while using different tag", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["tag_1"])->put($key, "value_1", 5);
    expect($cache->get($key))->toBe("value_1");
    expect($cache->tags(["tag_1"])->get($key))->toBe("value_1");
    expect($cache->tags(["tag_2"])->get($key))->toBe("value_1");

    $cache->tags(["tag_2"])->put($key, "value_2", 5);
    expect($cache->get($key))->toBe("value_2");
    expect($cache->tags(["tag_1"])->get($key))->toBe("value_2");
    expect($cache->tags(["tag_2"])->get($key))->toBe("value_2");

    $cache->put($key, "value_3", 5);
    expect($cache->get($key))->toBe("value_3");
    expect($cache->tags(["tag_1"])->get($key))->toBe("value_3");
    expect($cache->tags(["tag_2"])->get($key))->toBe("value_3");
});

it("#18 can prune stale tags with command", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["stale"])->put($key, "value", 1);

    sleep(2);

    expect($cache->get($key))->toBeNull();

    $tagSet = $cache->tags(["stale"])->getTags();
    $entries = $tagSet->entries()->all();

    expect($entries)->toBe([
        0 => $key->toString()
    ]);

    $exitCode = Artisan::call("cache:prune-stale-tags");
    expect($exitCode)->toBe(0);

    $newEntries = $tagSet->entries()->all();
    expect($newEntries)->toBe([]);
});

it("#19 can put and get many items", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["tag_1"])->putMany(["{$key}_1" => "value", "{$key}_2" => "value"], 5);

    expect($cache->tags(["tag_1"])->get(["{$key}_1", "{$key}_2"]))->toBe([
        "{$key}_1" => "value",
        "{$key}_2" => "value"
    ]);
});

it("#20 can put forever", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["tag_1"])->forever($key, "value");

    expect($cache->tags(["tag_1"]))->get($key)->toBe("value");
});


it("#21 can remember", function () {
    $cache = $this->cache();
    $key = $this->key();

    $cache->tags(["tag_1"])->remember($key, 5, function () {
        return "value";
    });

    expect($cache->tags(["tag_1"]))->remember($key, 5, function () {
        return "value";
    })->toBe("value");
});
