<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => Cache::getInstance()->flush());

it('gets and sets through the fluent leaf', function (): void {
    Options::option(ThemeOption::class)->set('dark');

    expect(Options::option(ThemeOption::class)->get())->toBe('dark')
        ->and(Options::option(ThemeOption::class)->value())->toBe('dark');
});

it('exposes default key and readable', function (): void {
    $context = Options::option(ThemeOption::class);

    expect($context->default())->toBe('light')
        ->and($context->key())->toBe('theme')
        ->and($context->readable())->toBe('Theme');
});

it('reports has and forgets', function (): void {
    expect(Options::option(ThemeOption::class)->has())->toBeFalse();

    Options::option(ThemeOption::class)->set('dark');

    expect(Options::option(ThemeOption::class)->has())->toBeTrue();

    Options::option(ThemeOption::class)->forget();

    expect(Options::option(ThemeOption::class)->has())->toBeFalse()
        ->and(Options::option(ThemeOption::class)->get())->toBe('light');
});

it('resets through the fluent leaf', function (): void {
    Options::option(ThemeOption::class)->set('dark');
    Options::option(ThemeOption::class)->reset();

    expect(Options::option(ThemeOption::class)->get())->toBe('light');
});

it('remembers and does not call the closure when already set', function (): void {
    Options::option(ThemeOption::class)->set('dark');

    $called = false;

    $value = Options::option(ThemeOption::class)->remember(function () use (&$called): string {
        $called = true;

        return 'computed';
    });

    expect($value)->toBe('dark')
        ->and($called)->toBeFalse();
});

it('remembers and calls the closure when unset', function (): void {
    $value = Options::option(ThemeOption::class)->remember(fn (): string => 'computed');

    expect($value)->toBe('computed')
        ->and(Options::get(ThemeOption::class))->toBe('computed');
});

it('builds a leaf scoped to an owner', function (): void {
    $user = User::create();

    Options::for($user)->option(ThemeOption::class)->set('dark');

    expect(Options::for($user)->option(ThemeOption::class)->get())->toBe('dark')
        ->and(Options::option(ThemeOption::class)->get())->toBe('light');
});
