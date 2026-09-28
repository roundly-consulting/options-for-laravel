<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\CollectionOption;

beforeEach(fn () => app(Cache::class)->flush());

it('has custom key', function () {
    expect(CollectionOption::make())->key()->toBe('custom-key');
});

it('has custom readable name', function () {
    expect(CollectionOption::make())->readable()->toBe('Custom collection option');
});

it('has collection as default value', function () {
    expect(CollectionOption::make()->default())
        ->toBeInstanceOf(Collection::class)
        ->toArray()
        ->toBe(['default' => 'yes']);
});

it('casts value to collection as default', function () {
    expect(CollectionOption::make())->castAs()->toBe('collection');
});

it('it returns default value when option is not set in database', function () {
    $option = CollectionOption::make();

    expect($option)->value()->toArray()->toBe(['default' => 'yes']);
});

it('sets value', function () {
    $option = CollectionOption::make();

    $option->set(collect([
        'my' => 'value',
    ]));

    $this->assertDatabaseHas('options', [
        'owner_id' => null,
        'owner_type' => null,
        'key' => 'custom-key',
        'value' => json_encode(['my' => 'value']),
    ]);
});

it('returns value', function () {
    $option = CollectionOption::make();

    Option::create([
        'key' => 'custom-key',
        'value' => json_encode(['my' => 'another-value']),
    ]);

    expect($option->value())
        ->toArray()
        ->toBe(['my' => 'another-value']);
});
