<?php

declare(strict_types=1);

use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Support\StoredValue;
use RoundlyConsulting\Options\Tests\Options\FlagOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    app(Cache::class)->flush();
    app(OptionStore::class)->flush();
});

it('serves a value from the persistent store without hitting the database', function (): void {
    Options::set(ThemeOption::class, 'dark');

    // Drop only the in-request memo cache, simulating a fresh request.
    app(Cache::class)->flush();

    DB::enableQueryLog();

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(DB::getQueryLog())->toHaveCount(0);

    DB::disableQueryLog();
});

it('updates the persistent value on set', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::set(ThemeOption::class, 'light');

    app(Cache::class)->flush();

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('falls back to the database when cache is disabled', function (): void {
    config()->set('options.cache.enabled', false);

    Options::set(ThemeOption::class, 'dark');
    app(Cache::class)->flush();

    DB::enableQueryLog();

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(DB::getQueryLog())->toHaveCount(1);

    DB::disableQueryLog();
});

it('invalidates the persistent store on forget', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);

    app(Cache::class)->flush();

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('caches forever when ttl is null', function (): void {
    config()->set('options.cache.ttl', null);

    Options::set(ThemeOption::class, 'dark');
    app(Cache::class)->flush();

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('uses a named store when configured', function (): void {
    config()->set('options.cache.store', 'array');

    Options::set(ThemeOption::class, 'dark');
    app(Cache::class)->flush();

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
    app(Cache::class)->flush();

    // A foreign payload under the option's key: an older layout, a hand edit.
    CacheFacade::tags('options')->put('options:'.CacheFacade::get('options:generation').':'.OptionStore::fingerprint('theme'), 'light', 60);

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('accepts only a stored-value cache payload', function (): void {
    expect(StoredValue::fromCache('dark'))->toBeNull()
        ->and(StoredValue::fromCache(['exists' => 'yes']))->toBeNull()
        ->and(StoredValue::fromCache(['exists' => true, 'raw' => 5]))->toBeNull()
        ->and(StoredValue::fromCache(['exists' => true, 'raw' => null]))->toEqual(new StoredValue(true))
        ->and(StoredValue::fromCache(['exists' => false, 'raw' => 'x']))->toEqual(StoredValue::missing());
});

it('starts a fresh generation when the generation key is evicted', function (): void {
    Options::set(ThemeOption::class, 'dark');
    DB::table('options')->where('key', 'theme')->update(['value' => 'edited']);

    CacheFacade::forget('options:generation');
    app(Cache::class)->flush();

    expect(Options::get(ThemeOption::class))->toBe('edited')
        ->and(CacheFacade::get('options:generation'))->toBeString();
});

/**
 * Run the callback as another request would — its own in-request memo — right after this
 * request's next read of the options table, i.e. between its cache miss and its cache fill.
 */
function afterNextOptionsRead(Closure $otherRequest): void
{
    $fired = false;

    DB::listen(function (QueryExecuted $query) use (&$fired, $otherRequest): void {
        if ($fired || preg_match('/^select .* from [`"]?options[`"]?/i', $query->sql) !== 1) {
            return;
        }

        $fired = true;
        $memo = app(Cache::class);
        app()->instance(Cache::class, new Cache);

        try {
            $otherRequest();
        } finally {
            app()->instance(Cache::class, $memo);
        }
    });
}

it('does not re-cache a value read before a concurrent write', function (): void {
    // Regression (2026-10-05 chat review, C-2): the reader put() the old row it had read
    // over the writer's fresh entry, so every request served the old value for the TTL.
    Options::set(ThemeOption::class, 'light');
    Options::flushCache();

    afterNextOptionsRead(fn () => Options::set(ThemeOption::class, 'dark'));

    $read = Options::get(ThemeOption::class);
    app()->forgetInstance(Cache::class);

    expect($read)->toBe('light')
        ->and(Option::query()->where('key', 'theme')->value('value'))->toBe('dark')
        ->and(Options::get(ThemeOption::class))->toBe('dark');
});

it('does not re-cache a value read before a concurrent forget', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::flushCache();

    afterNextOptionsRead(fn () => Options::forget(ThemeOption::class));

    $read = Options::get(ThemeOption::class);
    app()->forgetInstance(Cache::class);

    expect($read)->toBe('dark')
        ->and(Option::query()->count())->toBe(0)
        ->and(Options::get(ThemeOption::class))->toBe('light');
});

it('does not re-cache a value read before a concurrent import', function (): void {
    Options::set(ThemeOption::class, 'light');
    Options::set(FlagOption::class, false);
    Options::flushCache();

    afterNextOptionsRead(fn () => Options::import([
        ['key' => 'theme', 'value' => 'dark'],
        ['key' => 'flag', 'value' => true],
    ]));

    $read = Options::get(ThemeOption::class);
    Options::get(FlagOption::class);
    app()->forgetInstance(Cache::class);
    DB::enableQueryLog();

    expect($read)->toBe('light')
        ->and(Options::get(ThemeOption::class))->toBe('dark')
        ->and(Options::get(FlagOption::class))->toBeTrue()
        ->and(DB::getQueryLog())->toBe([]);
});

it('lands a write where fresh requests read, after the generation split', function (): void {
    // Regression (2026-10-05 chat review, C-3): with the generation key missing, two
    // requests minted different generations and the last one stored won. A write in the
    // losing request went under its own, memoised generation — where nobody reads — so the
    // winner's cached old value kept being served.
    Options::set(ThemeOption::class, 'light');
    Options::flushCache();
    $requestA = new Cache;
    $requestB = new Cache;

    CacheFacade::forget('options:generation');
    app()->instance(Cache::class, $requestA);
    Options::get(ThemeOption::class);

    // B read the key before A's mint landed: it mints its own, which is stored last.
    CacheFacade::forget('options:generation');
    app()->instance(Cache::class, $requestB);
    Options::get(ThemeOption::class);

    app()->instance(Cache::class, $requestA);
    Options::set(ThemeOption::class, 'dark');

    app()->instance(Cache::class, new Cache);

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('adopts a generation another request minted first', function (): void {
    Options::set(ThemeOption::class, 'light');
    Options::flushCache();
    CacheFacade::forget('options:generation');

    // Another request mints between this one's miss on the key and its own mint.
    Event::listen(CacheMissed::class, function (CacheMissed $event): void {
        if ($event->key === 'options:generation') {
            CacheFacade::forever('options:generation', 'minted-elsewhere');
        }
    });

    Options::get(ThemeOption::class);

    expect(CacheFacade::get('options:generation'))->toBe('minted-elsewhere')
        ->and(CacheFacade::tags('options')->get('options:minted-elsewhere:'.OptionStore::fingerprint('theme')))
        ->toBe(['exists' => true, 'raw' => 'light']);
});
