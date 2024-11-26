<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;

it('can store and retrieve an item from the cache', function () {
    Cache::put('test_key', 'test_value', 60);
    $value = Cache::get('test_key');
    expect($value)->toBe('test_value');
});

it('can check if an item exists in the cache', function () {
    Cache::put('exists_key', 'exists_value', 60);
    expect(Cache::has('exists_key'))->toBeTrue();
    expect(Cache::has('missing_key'))->toBeFalse();
});

it('can check if an item is missing from the cache', function () {
    Cache::put('exists_key', 'exists_value', 60);
    expect(Cache::missing('exists_key'))->toBeFalse();
    expect(Cache::missing('missing_key'))->toBeTrue();
});

it('can store and retrieve multiple items from the cache', function () {
    Cache::putMany(['key1' => 'value1', 'key2' => 'value2'], 60);
    $values = Cache::many(['key1', 'key2']);
    expect($values)->toBe(['key1' => 'value1', 'key2' => 'value2']);
});

it('can increment and decrement a value in the cache', function () {
    Cache::put('counter', 10, 60);
    Cache::increment('counter', 5);
    expect(Cache::get('counter'))->toEqual(15);
    Cache::decrement('counter', 3);
    expect(Cache::get('counter'))->toEqual(12);
});

it('can remove an item from the cache', function () {
    Cache::put('delete_key', 'delete_value', 60);
    Cache::forget('delete_key');
    expect(Cache::get('delete_key'))->toBeNull();
});

it('can flush all items from the cache', function () {
    Cache::putMany(['key1' => 'value1', 'key2' => 'value2'], 60);
    Cache::flush();
    expect(Cache::get('key1'))->toBeNull();
});

it('can handle distributed locking', function () {
    $lock = Cache::lock('my_lock', 10);
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

    $newLock = Cache::lock('my_lock', 10);
    expect($newLock->get())->toBeTrue();
    $newLock->release();
});

it("can store and invalidate tagged items", function () {
    $cache = Cache::tags(["tag1"]);

    $cache->put("tagged_key_1", "tagged_value_1", 60);

    $cache = Cache::tags(["tag2"]);

    $cache->put("tagged_key_2", "tagged_value_2", 60);

    expect(Cache::tags(["tag1"])->get("tagged_key_1"))->toBe("tagged_value_1");

    expect(Cache::tags(["tag2"])->get("tagged_key_2"))->toBe("tagged_value_2");

    // Invalidate tag1
    Cache::tags(["tag1"])->flush();

    expect(Cache::tags(["tag1"])->get("tagged_key_1"))->toBeNull();

    expect(Cache::tags(["tag2"])->get("tagged_key_2"))->toBe("tagged_value_2");
});

it("can add an item only if it doesn't exist", function () {
    // Add a new key
    $added = Cache::add("add_key", "add_value", 60);

    // Ensure it was added
    expect($added)->toBeTrue();

    // Try adding again (should fail since it already exists)
    $addedAgain = Cache::add("add_key", "new_value", 60);

    // Ensure it wasn't added again
    expect($addedAgain)->toBeFalse();
});

it("can pull an item from the cache", function () {
    // Store a value
    Cache::put("pull_key", "pull_value", 60);

    // Pull (retrieve and delete) the value
    $pulledValue = Cache::pull("pull_key");

    // Ensure it's retrieved correctly
    expect($pulledValue)->toBe("pull_value");

    // Ensure it's deleted after pulling
    expect(Cache::get("pull_key"))->toBeNull();
});

it("can remember and remember forever", function () {

    // Remember: Retrieve or store value for a TTL
    $rememberedValue = Cache::remember(
        "remember_key",
        60,
        fn() => "remembered_value"
    );

    // Check remembered value
    expect($rememberedValue)->toBe("remembered_value");

    // Remember Forever: Retrieve or store value indefinitely
    $rememberedForeverValue = Cache::rememberForever(
        "remember_forever_key",
        fn() => "forever_value"
    );

    // Check remembered forever value
    expect($rememberedForeverValue)->toBe("forever_value");
});

it('can store and invalidate an item with a single tag', function () {
    // Store an item with a single tag
    Cache::tags(['tag1'])->put('single_tag_item', 'value1', 60);

    // Retrieve the item
    expect(Cache::tags(['tag1'])->get('single_tag_item'))->toBe('value1');

    // Invalidate the tag
    Cache::tags(['tag1'])->flush();

    // Ensure the item is invalidated
    expect(Cache::tags(['tag1'])->get('single_tag_item'))->toBeNull();
});

it('can store and invalidate an item with multiple tags', function () {
    // Store an item with multiple tags
    Cache::tags(['tag1', 'tag2'])->put('multi_tag_item', 'value2', 60);

    // Retrieve the item
    expect(Cache::tags(['tag1'])->get('multi_tag_item'))->toBe('value2');
    expect(Cache::tags(['tag2'])->get('multi_tag_item'))->toBe('value2');

    // Invalidate one tag (should remove the item)
    Cache::tags(['tag1'])->flush();

    // Ensure the item is not available under 'tag2'
    expect(Cache::tags(['tag2'])->get('multi_tag_item'))->toBeNull();

    // Invalidate the second tag
    Cache::tags(['tag2'])->flush();

    // Ensure the item is now fully invalidated
    expect(Cache::tags(['tag2'])->get('multi_tag_item'))->toBeNull();
});

it('can store multiple items with shared tags and invalidate them', function () {
    // Store two items with shared tags
    Cache::tags(['shared_tag'])->putMany([
        'item1' => 'value1',
        'item2' => 'value2',
    ], 60);

    // Retrieve both items
    expect(Cache::tags(['shared_tag'])->get('item1'))->toBe('value1');
    expect(Cache::tags(['shared_tag'])->get('item2'))->toBe('value2');

    // Invalidate the shared tag
    Cache::tags(['shared_tag'])->flush();

    // Ensure both items are invalidated
    expect(Cache::tags(['shared_tag'])->get('item1'))->toBeNull();
    expect(Cache::tags(['shared_tag'])->get('item2'))->toBeNull();
});

it('can invalidate multiple tags at once', function () {
    // Store items with different tags
    Cache::tags(['tagA'])->putMany([
        'itemA' => 'valueA',
        'itemB' => 'valueB',
    ], 60);

    Cache::tags(['tagB'])->putMany([
        'itemC' => 'valueC',
        'itemD' => 'valueD',
    ], 60);

    // Retrieve all items
    expect(Cache::tags(['tagA'])->many(['itemA', 'itemB']))->toBe([
        'itemA' => 'valueA',
        'itemB' => 'valueB',
    ]);

    expect(Cache::tags(['tagB'])->many(['itemC', 'itemD']))->toBe([
        'itemC' => 'valueC',
        'itemD' => 'valueD',
    ]);

    // Invalidate both tags at once
    Cache::tags(['tagA', 'tagB'])->flush();

    // Ensure all items are invalidated
    expect(Cache::tags(['tagA'])->many(['itemA', 'itemB']))->toBe([
        'itemA' => null,
        'itemB' => null,
    ]);

    expect(Cache::tags(['tagB'])->many(['itemC', 'itemD']))->toBe([
        'itemC' => null,
        'itemD' => null,
    ]);
});

it("can handle retrieving tagged items in different order", function () {
    // Store an item with two tags
    Cache::tags(["diff_tag_1", "diff_tag_2"])->put("diff_value", "value", 60);

    // Make sure single tag is working
    expect(Cache::tags(["diff_tag_2"])->get("diff_value"))->toBe("value");

    // Make sure tag order doesn't matter
    expect(Cache::tags(["diff_tag_2", "diff_tag_1"])->get("diff_value"))->toBe("value");

    // No value without tags
    expect(Cache::get("diff_value"))->toBeNull();
});
