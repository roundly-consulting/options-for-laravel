<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\SimpleOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => app(Cache::class)->flush());

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

it('returns the stored value to both of two racing first remember() calls', function (): void {
    // Regression (2026-10-05 chat review, C-10): remember() was has() + set(), so a second
    // first caller that stored in between got its own value back, then had it overwritten.
    $seen = [];
    Options::observe(ThemeOption::class, function (mixed $value) use (&$seen): void {
        $seen[] = $value;
    });

    $inner = null;
    $outer = Options::remember(ThemeOption::class, function () use (&$inner): string {
        // The other caller runs between this one's has() and its write.
        $inner = Options::remember(ThemeOption::class, fn (): string => 'from-b');

        return 'from-a';
    });

    Options::flushCache();

    expect($inner)->toBe('from-b')
        ->and($outer)->toBe('from-b')
        ->and(Options::get(ThemeOption::class))->toBe('from-b')
        ->and($seen)->toBe(['from-b']);

    Options::flushObservers();
});

it('returns the winner of a lost first-insert race from remember()', function (): void {
    $raced = false;
    DB::connection()->beforeStartingTransaction(function (Connection $connection) use (&$raced): void {
        // The other request's row lands right before this one's insert savepoint.
        if (! $raced && $connection->transactionLevel() === 1) {
            $raced = true;
            DB::table('options')->insert(['key' => 'theme', 'value' => 'from-b', 'owner_scope' => OptionStore::scope()]);
        }
    });

    expect(Options::remember(ThemeOption::class, fn (): string => 'from-a'))->toBe('from-b')
        ->and($raced)->toBeTrue()
        ->and(Option::query()->where('key', 'theme')->value('value'))->toBe('from-b');
});

it('remembers over a forgotten value', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);

    expect(Options::remember(ThemeOption::class, fn (): string => 'blue'))->toBe('blue')
        ->and(Option::withTrashed()->where('key', 'theme')->count())->toBe(1);

    Options::flushCache();

    expect(Options::get(ThemeOption::class))->toBe('blue');
});
