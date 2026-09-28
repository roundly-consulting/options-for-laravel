<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\LocaleOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => app(Cache::class)->flush());

it('reads and writes through the scope object', function (): void {
    $user = User::create();

    Options::for($user)->set(ThemeOption::class, 'dark');

    expect(Options::for($user)->get(ThemeOption::class))->toBe('dark');
});

it('reports whether a value is stored', function (): void {
    expect(Options::for(null)->has(ThemeOption::class))->toBeFalse();

    Options::set(ThemeOption::class, 'dark');

    expect(Options::for(null)->has(ThemeOption::class))->toBeTrue();
});

it('forgets a value reverting to default', function (): void {
    Options::set(ThemeOption::class, 'dark');

    Options::for(null)->forget(ThemeOption::class);

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('resets a value reverting to default', function (): void {
    Options::set(ThemeOption::class, 'dark');

    Options::for(null)->reset(ThemeOption::class);

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('reads many at once', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::set(LocaleOption::class, 'sk');

    expect(Options::for(null)->many([ThemeOption::class, LocaleOption::class]))
        ->toBe([ThemeOption::class => 'dark', LocaleOption::class => 'sk']);
});

it('writes many at once', function (): void {
    Options::for(null)->setMany([
        ThemeOption::class => 'dark',
        LocaleOption::class => 'sk',
    ]);

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(Options::get(LocaleOption::class))->toBe('sk');
});

it('handles empty many input', function (): void {
    expect(Options::for(null)->many([]))->toBe([]);

    Options::for(null)->setMany([]);

    expect(Options::all())->toHaveCount(0);
});

it('eager loads everything for a scope', function (): void {
    $user = User::create();

    Options::for($user)->set(ThemeOption::class, 'dark');
    Options::for($user)->set(LocaleOption::class, 'sk');
    Options::set(ThemeOption::class, 'global');

    $all = Options::for($user)->all();

    expect($all)->toBeInstanceOf(Collection::class)
        ->and($all->get('theme'))->toBe('dark')
        ->and($all->get('locale'))->toBe('sk')
        ->and($all)->toHaveCount(2);
});

it('remembers a value through the scope object', function (): void {
    $value = Options::for(null)->remember(ThemeOption::class, fn (): string => 'computed');

    expect($value)->toBe('computed')
        ->and(Options::get(ThemeOption::class))->toBe('computed');
});
