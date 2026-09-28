<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    app(Cache::class)->flush();
    Options::register(['theme' => ThemeOption::class]);
});

it('resolves a registered key to its class', function (): void {
    expect(Options::resolveClass('theme'))->toBe(ThemeOption::class);
});

it('resolves a class-string straight through', function (): void {
    expect(Options::resolveClass(ThemeOption::class))->toBe(ThemeOption::class);
});

it('reads and writes by registered key', function (): void {
    Options::key('theme')->set('dark');

    expect(Options::key('theme')->get())->toBe('dark')
        ->and(Options::get('theme'))->toBe('dark');
});

it('reads a registered key scoped to an owner', function (): void {
    $user = User::create();

    Options::for($user)->key('theme')->set('dark');

    expect(Options::for($user)->get('theme'))->toBe('dark');
});

it('exposes registered options', function (): void {
    expect(Options::registered())->toHaveKey('theme', ThemeOption::class);
});

it('throws for an unknown key', function (): void {
    Options::resolveClass('nope');
})->throws(InvalidOptionClassName::class);

it('seeds the registry from config', function (): void {
    config()->set('options.registry', ['locale' => ThemeOption::class]);

    // Force a fresh manager so the config seed runs.
    Options::clearResolvedInstance(OptionsManager::class);
    app()->forgetInstance(OptionsManager::class);

    expect(Options::resolveClass('locale'))->toBe(ThemeOption::class);
});
