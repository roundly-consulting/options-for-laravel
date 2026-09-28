<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => Options::flushCache());

/**
 * Insert a row straight into the table, as a concurrent request would.
 */
function insertRawOption(string $key, string $value, ?User $owner = null): void
{
    DB::table('options')->insert([
        'key' => $key,
        'value' => $value,
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
        'owner_scope' => OptionStore::scope($owner?->getMorphClass(), $owner?->getKey()),
    ]);
}

it('refuses a second row for the same global option', function (): void {
    Options::set(ThemeOption::class, 'dark');

    insertRawOption('theme', 'duplicate');
})->throws(UniqueConstraintViolationException::class);

it('refuses a second row for the same owned option', function (): void {
    $user = User::create();
    Options::set(ThemeOption::class, 'dark', $user);

    insertRawOption('theme', 'duplicate', $user);
})->throws(UniqueConstraintViolationException::class);

it('keeps one row when a concurrent first write wins the insert race', function (?bool $owned): void {
    $user = $owned ? User::create() : null;
    $raced = false;

    // Between this request's lookup (no row yet) and its insert, another one
    // commits its own: the hook runs just before the insert's savepoint opens.
    DB::connection()->beforeStartingTransaction(function () use (&$raced, $user): void {
        if (! $raced) {
            $raced = true;
            insertRawOption('theme', 'from-the-other-request', $user);
        }
    });

    Options::set(ThemeOption::class, 'dark', $user);

    expect($raced)->toBeTrue()
        ->and(Option::withTrashed()->where('key', 'theme')->count())->toBe(1)
        ->and(Option::query()->where('key', 'theme')->value('value'))->toBe('dark')
        ->and(Options::get(ThemeOption::class, $user))->toBe('dark');
})->with(['global' => [false], 'owned' => [true]]);

it('reuses a forgotten row instead of adding one', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);
    Options::set(ThemeOption::class, 'blue');

    expect(Option::withTrashed()->where('key', 'theme')->count())->toBe(1)
        ->and(Options::get(ThemeOption::class))->toBe('blue');
});

it('imports over a forgotten row', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);

    Options::import([['key' => 'theme', 'value' => 'imported']]);

    expect(Option::withTrashed()->where('key', 'theme')->count())->toBe(1)
        ->and(Options::get(ThemeOption::class))->toBe('imported');
});
