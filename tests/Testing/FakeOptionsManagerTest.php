<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => Cache::getInstance()->flush());

it('does not write to the database', function (): void {
    Options::fake();

    Options::set(ThemeOption::class, 'dark');

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(Option::query()->count())->toBe(0);
});

it('returns the default for an unset option', function (): void {
    Options::fake();

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('asserts an option was set', function (): void {
    $fake = Options::fake();

    Options::set(ThemeOption::class, 'dark');

    $fake->assertSet(ThemeOption::class);
    $fake->assertSet(ThemeOption::class, 'dark');
});

it('fails assertSet when nothing was set', function (): void {
    $fake = Options::fake();

    expect(fn () => $fake->assertSet(ThemeOption::class))
        ->toThrow(AssertionFailedError::class);
});

it('asserts nothing was set', function (): void {
    $fake = Options::fake();

    $fake->assertNothingSet();

    Options::set(ThemeOption::class, 'dark');

    expect(fn () => $fake->assertNothingSet())->toThrow(AssertionFailedError::class);
});

it('tracks has and forget in memory', function (): void {
    $fake = Options::fake();

    expect(Options::has(ThemeOption::class))->toBeFalse();

    Options::set(ThemeOption::class, 'dark');

    expect(Options::has(ThemeOption::class))->toBeTrue();

    Options::forget(ThemeOption::class);

    expect(Options::has(ThemeOption::class))->toBeFalse();

    $fake->assertForgotten(ThemeOption::class);
});

it('resets and remembers in memory', function (): void {
    Options::fake();

    Options::set(ThemeOption::class, 'dark');
    Options::reset(ThemeOption::class);

    expect(Options::get(ThemeOption::class))->toBe('light');

    $value = Options::remember(ThemeOption::class, fn (): string => 'computed');

    expect($value)->toBe('computed')
        ->and(Options::get(ThemeOption::class))->toBe('computed');
});

it('scopes fake values per owner', function (): void {
    $fake = Options::fake();
    $user = User::create();

    Options::for($user)->set(ThemeOption::class, 'dark');

    expect(Options::for($user)->get(ThemeOption::class))->toBe('dark')
        ->and(Options::get(ThemeOption::class))->toBe('light')
        ->and(Options::for($user)->all()->get('theme'))->toBe('dark');

    $fake->assertSet(ThemeOption::class, 'dark', $user);
});

it('flushes the fake cache without error', function (): void {
    $fake = Options::fake();

    $fake->flushCache();

    expect(true)->toBeTrue();
});

it('remember returns the existing value without calling the closure', function (): void {
    Options::fake();

    Options::set(ThemeOption::class, 'dark');

    $called = false;

    $value = Options::remember(ThemeOption::class, function () use (&$called): string {
        $called = true;

        return 'computed';
    });

    expect($value)->toBe('dark')
        ->and($called)->toBeFalse();
});

it('does not match assertSet across different keys or owners', function (): void {
    $fake = Options::fake();
    $user = User::create();

    Options::set(ThemeOption::class, 'dark');

    expect(fn () => $fake->assertSet(ThemeOption::class, 'dark', $user))
        ->toThrow(AssertionFailedError::class);
});

it('fails assertForgotten when nothing was forgotten', function (): void {
    $fake = Options::fake();

    expect(fn () => $fake->assertForgotten(ThemeOption::class))
        ->toThrow(AssertionFailedError::class);
});
