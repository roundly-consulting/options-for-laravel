<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Support\StoredValue;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    Cache::getInstance()->flush();
    app(OptionStore::class)->flush();
});

it('serves a value from the persistent store without hitting the database', function (): void {
    Options::set(ThemeOption::class, 'dark');

    // Drop only the in-request memo cache, simulating a fresh request.
    Cache::getInstance()->flush();

    DB::enableQueryLog();

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(DB::getQueryLog())->toHaveCount(0);

    DB::disableQueryLog();
});

it('updates the persistent value on set', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::set(ThemeOption::class, 'light');

    Cache::getInstance()->flush();

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('falls back to the database when cache is disabled', function (): void {
    config()->set('options.cache.enabled', false);

    Options::set(ThemeOption::class, 'dark');
    Cache::getInstance()->flush();

    DB::enableQueryLog();

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(DB::getQueryLog())->toHaveCount(1);

    DB::disableQueryLog();
});

it('invalidates the persistent store on forget', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);

    Cache::getInstance()->flush();

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('caches forever when ttl is null', function (): void {
    config()->set('options.cache.ttl', null);

    Options::set(ThemeOption::class, 'dark');
    Cache::getInstance()->flush();

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('uses a named store when configured', function (): void {
    config()->set('options.cache.store', 'array');

    Options::set(ThemeOption::class, 'dark');
    Cache::getInstance()->flush();

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('is a no-op for forget and flush when disabled', function (): void {
    config()->set('options.cache.enabled', false);

    $store = app(OptionStore::class);
    $store->put('x', new StoredValue(true, 'y'));
    $store->forget('x');
    $store->flush();

    expect($store->isEnabled())->toBeFalse();
});

it('flushes the persistent store through the manager', function (): void {
    Options::set(ThemeOption::class, 'dark');

    Options::flushCache();

    DB::enableQueryLog();
    Options::get(ThemeOption::class);

    expect(DB::getQueryLog())->not->toHaveCount(0);

    DB::disableQueryLog();
});

it('re-reads an entry that is not a stored-value payload', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Cache::getInstance()->flush();

    // A foreign payload under the option's key: an older layout, a hand edit.
    CacheFacade::tags('options')->put('options:'.OptionStore::fingerprint('theme'), 'light', 60);

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('accepts only a stored-value cache payload', function (): void {
    expect(StoredValue::fromCache('dark'))->toBeNull()
        ->and(StoredValue::fromCache(['exists' => 'yes']))->toBeNull()
        ->and(StoredValue::fromCache(['exists' => true, 'raw' => 5]))->toBeNull()
        ->and(StoredValue::fromCache(['exists' => true, 'raw' => null]))->toEqual(new StoredValue(true))
        ->and(StoredValue::fromCache(['exists' => false, 'raw' => 'x']))->toEqual(StoredValue::missing());
});
