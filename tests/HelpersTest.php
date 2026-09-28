<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    app(Cache::class)->flush();
    Options::register(['theme' => ThemeOption::class]);
});

it('returns the manager when called with no arguments', function (): void {
    expect(options())->toBeInstanceOf(OptionsManager::class);
});

it('reads a value by class-string', function (): void {
    Options::set(ThemeOption::class, 'dark');

    expect(options(ThemeOption::class))->toBe('dark');
});

it('reads a value by registered key', function (): void {
    Options::set(ThemeOption::class, 'dark');

    expect(options('theme'))->toBe('dark');
});

it('reads a scoped value', function (): void {
    $user = User::create();

    Options::for($user)->set(ThemeOption::class, 'dark');

    expect(options(ThemeOption::class, $user))->toBe('dark');
});

it('chains into the manager', function (): void {
    options()->set(ThemeOption::class, 'dark');

    expect(options(ThemeOption::class))->toBe('dark');
});

it('aliases setting to options', function (): void {
    Options::set(ThemeOption::class, 'dark');

    expect(setting('theme'))->toBe('dark')
        ->and(setting())->toBeInstanceOf(OptionsManager::class);
});
