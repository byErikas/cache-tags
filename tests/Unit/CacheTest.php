<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;

it("#1 can store and retrieve an item from the cache", function () {
    Cache::store("redis")->put("test_key", "test_value", 60);
    $value = Cache::store("redis")->get("test_key");
    expect($value)->toBe("test_value");
});

it("#2 can check if an item exists in the cache", function () {
    Cache::store("redis")->put("exists_key", "exists_value", 60);
    expect(Cache::store("redis")->has("exists_key"))->toBeTrue();
    expect(Cache::store("redis")->has("missing_key"))->toBeFalse();
});

it("#3 can check if an item is missing from the cache", function () {
    Cache::store("redis")->put("exists_key", "exists_value", 60);
    expect(Cache::store("redis")->missing("exists_key"))->toBeFalse();
    expect(Cache::store("redis")->missing("missing_key"))->toBeTrue();
});

it("#4 can store and retrieve multiple items from the cache", function () {
    Cache::store("redis")->putMany(["key1" => "value1", "key2" => "value2"], 60);
    $values = Cache::store("redis")->many(["key1", "key2"]);
    expect($values)->toBe(["key1" => "value1", "key2" => "value2"]);
});

it("#5 can increment and decrement a value in the cache", function () {
    Cache::store("redis")->put("counter", 10, 60);
    Cache::store("redis")->increment("counter", 5);
    expect(Cache::store("redis")->get("counter"))->toEqual(15);
    Cache::store("redis")->decrement("counter", 3);
    expect(Cache::store("redis")->get("counter"))->toEqual(12);
});

it("#6 can remove an item from the cache", function () {
    Cache::store("redis")->put("delete_key", "delete_value", 60);
    Cache::store("redis")->forget("delete_key");
    expect(Cache::store("redis")->get("delete_key"))->toBeNull();
});

it("#7 can flush all items from the cache", function () {
    Cache::store("redis")->putMany(["key1" => "value1", "key2" => "value2"], 60);
    Cache::store("redis")->flush();
    expect(Cache::store("redis")->get("key1"))->toBeNull();
});

it("#8 can handle distributed locking", function () {
    $lock = Cache::store("redis")->lock("my_lock", 10);
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

    $newLock = Cache::store("redis")->lock("my_lock", 10);
    expect($newLock->get())->toBeTrue();
    $newLock->release();
});

it("#9 can store and invalidate tagged items", function () {
    $cache = Cache::store("redis")->tags(["tag1"]);

    $cache->put("tagged_key_1", "tagged_value_1", 60);

    $cache = Cache::store("redis")->tags(["tag2"]);

    $cache->put("tagged_key_2", "tagged_value_2", 60);

    expect(Cache::store("redis")->tags(["tag1"])->get("tagged_key_1"))->toBe("tagged_value_1");

    expect(Cache::store("redis")->tags(["tag2"])->get("tagged_key_2"))->toBe("tagged_value_2");

    // Invalidate tag1
    Cache::store("redis")->tags(["tag1"])->flush();

    expect(Cache::store("redis")->tags(["tag1"])->get("tagged_key_1"))->toBeNull();

    expect(Cache::store("redis")->tags(["tag2"])->get("tagged_key_2"))->toBe("tagged_value_2");
});

it("#10 can add an item only if it doesn't exist", function () {
    // Add a new key
    $added = Cache::store("redis")->add("add_key", "add_value", 60);

    // Ensure it was added
    expect($added)->toBeTrue();

    // Try adding again (should fail since it already exists)
    $addedAgain = Cache::store("redis")->add("add_key", "new_value", 60);

    // Ensure it wasn't added again
    expect($addedAgain)->toBeFalse();
});

it("#11 can pull an item from the cache", function () {
    // Store a value
    Cache::store("redis")->put("pull_key", "pull_value", 60);

    // Pull (retrieve and delete) the value
    $pulledValue = Cache::store("redis")->pull("pull_key");

    // Ensure it"s retrieved correctly
    expect($pulledValue)->toBe("pull_value");

    // Ensure it"s deleted after pulling
    expect(Cache::store("redis")->get("pull_key"))->toBeNull();
});

it("#12 can remember and remember forever", function () {

    // Remember: Retrieve or store value for a TTL
    $rememberedValue = Cache::store("redis")->remember(
        "remember_key",
        60,
        fn() => "remembered_value"
    );

    // Check remembered value
    expect($rememberedValue)->toBe("remembered_value");

    // Remember Forever: Retrieve or store value indefinitely
    $rememberedForeverValue = Cache::store("redis")->rememberForever(
        "remember_forever_key",
        fn() => "forever_value"
    );

    // Check remembered forever value
    expect($rememberedForeverValue)->toBe("forever_value");
});

it("#13 can store and invalidate an item with a single tag", function () {
    // Store an item with a single tag
    Cache::store("redis")->tags(["tag1"])->put("single_tag_item", "value1", 60);

    // Retrieve the item
    expect(Cache::store("redis")->tags(["tag1"])->get("single_tag_item"))->toBe("value1");

    // Invalidate the tag
    Cache::store("redis")->tags(["tag1"])->flush();

    // Ensure the item is invalidated
    expect(Cache::store("redis")->tags(["tag1"])->get("single_tag_item"))->toBeNull();
});

it("#14 can store and invalidate an item with multiple tags", function () {
    // Store an item with multiple tags
    Cache::store("redis")->tags(["tag1", "tag2"])->put("multi_tag_item", "value2", 60);

    // Retrieve the item
    expect(Cache::store("redis")->tags(["tag1"])->get("multi_tag_item"))->toBe("value2");
    expect(Cache::store("redis")->tags(["tag2"])->get("multi_tag_item"))->toBe("value2");

    // Invalidate one tag (should remove the item)
    Cache::store("redis")->tags(["tag1"])->flush();

    // Ensure the item is not available under "tag2"
    expect(Cache::store("redis")->tags(["tag2"])->get("multi_tag_item"))->toBeNull();

    // Invalidate the second tag
    Cache::store("redis")->tags(["tag2"])->flush();

    // Ensure the item is now fully invalidated
    expect(Cache::store("redis")->tags(["tag2"])->get("multi_tag_item"))->toBeNull();
});

it("#15 can store multiple items with shared tags and invalidate them", function () {
    $store = Cache::store("redis");

    // Store two items with shared tags
    $store->tags(["shared_tag"])->putMany([
        "item1" => "value1",
        "item2" => "value2",
    ], 60);

    // Retrieve both items
    expect($store->tags(["shared_tag"])->get("item1"))->toBe("value1");
    expect($store->tags(["shared_tag"])->get("item2"))->toBe("value2");

    // Invalidate the shared tag
    $store->tags(["shared_tag"])->flush();

    // Ensure both items are invalidated
    expect($store->tags(["shared_tag"])->get("item1"))->toBeNull();
    expect($store->tags(["shared_tag"])->get("item2"))->toBeNull();
});

it("#16 can invalidate multiple tags at once", function () {
    $store = Cache::store("redis");

    // Store items with different tags
    $store->tags(["tagA"])->putMany([
        "itemA" => "valueA",
        "itemB" => "valueB",
    ], 60);

    $store->tags(["tagB"])->putMany([
        "itemC" => "valueC",
        "itemD" => "valueD",
    ], 60);

    // Retrieve all items
    expect($store->tags(["tagA"])->many(["itemA", "itemB"]))->toBe([
        "itemA" => "valueA",
        "itemB" => "valueB",
    ]);

    expect($store->tags(["tagB"])->many(["itemC", "itemD"]))->toBe([
        "itemC" => "valueC",
        "itemD" => "valueD",
    ]);

    // Invalidate both tags at once
    $store->tags(["tagA", "tagB"])->flush();

    // Ensure all items are invalidated
    expect($store->tags(["tagA"])->many(["itemA", "itemB"]))->toBe([
        "itemA" => null,
        "itemB" => null,
    ]);

    expect($store->tags(["tagB"])->many(["itemC", "itemD"]))->toBe([
        "itemC" => null,
        "itemD" => null,
    ]);
});

it("#17 can handle retrieving tagged items in different order", function () {
    $store = Cache::store("redis");

    // Store an item with two tags
    $store->tags(["t-17", "t-171"])->put("diff_value", "value", 60);

    // Make sure single tag is working
    expect($store->tags(["t-171"])->get("diff_value"))->toBe("value");

    // Make sure tag order doesn"t matter
    expect($store->tags(["t-171", "t-17"])->get("diff_value"))->toBe("value");

    // No value without tags
    expect($store->get("diff_value"))->toBeNull();
});

it("#18 can handle tagged namespaces", function () {
    $store = Cache::store("redis");

    $store->tags(["t-18-1"])->put("key", "t-18-1", 60);
    $store->tags(["t-18-2"])->put("key", "t-18-2", 60);
    $store->put("key", "t-18", 60);

    expect($store->tags(["t-18-1"])->get("key"))->toBe("t-18-1");
    expect($store->tags(["t-18-2"])->get("key"))->toBe("t-18-2");
    expect($store->get("key"))->toBe("t-18");
});
