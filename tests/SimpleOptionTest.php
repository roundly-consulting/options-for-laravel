<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\SimpleOption;

beforeEach(fn () => Cache::getInstance()->flush());

it('generates key from class name', function () {
    expect(SimpleOption::make())->key()->toBe('SimpleOption');
});

it('generates readable name', function () {
    expect(SimpleOption::make())->readable()->toBe('Simple Option');
});

it('has null as default value', function () {
    expect(SimpleOption::make())->default()->toBeNull();
});

it('casts value to string as default', function () {
    expect(SimpleOption::make())->castAs()->toBe('string');
});

it('it returns default value when option is not set in database', function () {
    $option = SimpleOption::make();

    expect($option)->value()->toBe($option->default());
});

it('sets value', function () {
    $option = SimpleOption::make();

    $option->set('my-value');

    $this->assertDatabaseHas('options', [
        'owner_id' => null,
        'owner_type' => null,
        'key' => 'SimpleOption',
        'value' => 'my-value',
    ]);
});

it('correctly returns new value after change', function () {
    $option = SimpleOption::make();

    $option->set('my-value');

    expect($option->value())->toBe('my-value');

    $option->set('another-value');

    expect($option->value())->toBe('another-value');
});

it('returns value', function () {
    $option = SimpleOption::make();

    Option::create([
        'key' => 'SimpleOption',
        'value' => 'awesome-value',
    ]);

    expect($option->value())->toBe('awesome-value');
});

it('returns different values for different owners', function () {
    $mom = User::create();
    $dad = User::create();

    Option::create([
        'key' => 'SimpleOption',
        'value' => 'moms-value',
        'owner_id' => $mom->id,
        'owner_type' => $mom->getMorphClass(),
    ]);

    Option::create([
        'key' => 'SimpleOption',
        'value' => 'dads-value',
        'owner_id' => $dad->id,
        'owner_type' => $dad->getMorphClass(),
    ]);

    expect(SimpleOption::for($mom)->value())->toBe('moms-value');
    expect(SimpleOption::for($dad)->value())->toBe('dads-value');
});

it('memorize return value in cache and does not perform another query to database', function () {
    $owner = User::create();

    Option::create([
        'key' => 'SimpleOption',
        'value' => 'Very good',
        'owner_id' => $owner->id,
        'owner_type' => $owner->getMorphClass(),
    ]);

    Option::create([
        'key' => 'SimpleOption',
        'value' => 'Global value',
    ]);

    DB::enableQueryLog();

    expect(SimpleOption::for($owner)->value())
        ->toBe('Very good')
        ->and(SimpleOption::for($owner)->value())
        ->toBe('Very good')
        ->and(SimpleOption::make()->value())
        ->toBe('Global value')
        ->and(DB::getQueryLog())
        ->toHaveCount(2);

    DB::disableQueryLog();
});

it('returns option instance from owner using HasOptions trait', function () {
    /** @var User $owner */
    $owner = User::create();

    Option::create([
        'key' => 'SimpleOption',
        'value' => 'Works fine!',
        'owner_id' => $owner->id,
        'owner_type' => $owner->getMorphClass(),
    ]);

    expect($owner->option(SimpleOption::class)->value())
        ->toBe('Works fine!');
});

it('throws exception when trying to resolve option from owner entity for unknown option', function () {
    /** @var User $owner */
    $owner = User::create();

    $owner->option('SomethingNotExisting');
})->throws(InvalidOptionClassName::class, 'Invalid class name provided for option selection. [SomethingNotExisting]');

it('throws exception when trying to resolve option from owner entity for class that does not implement OptionInterface', function () {
    /** @var User $owner */
    $owner = User::create();

    $owner->option(User::class);
})->throws(InvalidOptionClassName::class);
