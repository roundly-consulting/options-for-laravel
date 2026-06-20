<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\SimpleOption;

beforeEach(fn () => Cache::getInstance()->flush());

it('resolves an option through the manager', function (): void {
    $option = app(OptionsManager::class)->resolve(SimpleOption::class);

    expect($option)->toBeInstanceOf(SimpleOption::class);
});

it('resolves an option bound to an owner', function (): void {
    $owner = User::create();

    Option::create([
        'key' => 'SimpleOption',
        'value' => 'scoped',
        'owner_id' => $owner->id,
        'owner_type' => $owner->getMorphClass(),
    ]);

    expect(app(OptionsManager::class)->get(SimpleOption::class, $owner))->toBe('scoped');
});

it('throws when resolving an unknown class', function (): void {
    app(OptionsManager::class)->resolve('Nope');
})->throws(InvalidOptionClassName::class);

it('throws when resolving a class that does not implement the interface', function (): void {
    app(OptionsManager::class)->resolve(User::class);
})->throws(InvalidOptionClassName::class);

it('reads and writes through the facade', function (): void {
    Options::set(SimpleOption::class, 'from-facade');

    expect(Options::get(SimpleOption::class))->toBe('from-facade');

    $this->assertDatabaseHas('options', [
        'key' => 'SimpleOption',
        'value' => 'from-facade',
    ]);
});

it('flushes the cache through the facade', function (): void {
    Options::set(SimpleOption::class, 'cached');

    Options::flushCache();

    Option::query()->where('key', 'SimpleOption')->update(['value' => 'changed-directly']);

    expect(Options::get(SimpleOption::class))->toBe('changed-directly');
});
