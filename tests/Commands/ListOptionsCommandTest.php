<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\ArrayOption;
use RoundlyConsulting\Options\Tests\Options\FlagOption;
use RoundlyConsulting\Options\Tests\Options\SimpleOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => Cache::getInstance()->flush());

it('warns when nothing is registered', function (): void {
    $exit = Artisan::call('options:list');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('No options are registered');
});

it('lists registered options with values', function (): void {
    Options::register(['theme' => ThemeOption::class]);
    Options::set(ThemeOption::class, 'dark');

    $exit = Artisan::call('options:list');

    expect($exit)->toBe(0)
        ->and(Artisan::output())
        ->toContain('theme')
        ->toContain('dark');
});

it('renders various value types', function (): void {
    Options::register([
        'flag' => FlagOption::class,
        'array' => ArrayOption::class,
        'simple' => SimpleOption::class,
    ]);

    Options::set(FlagOption::class, true);
    Options::set(ArrayOption::class, ['a' => 1]);

    $exit = Artisan::call('options:list');

    expect($exit)->toBe(0)
        ->and(Artisan::output())
        ->toContain('true')
        ->toContain('—');
});

it('fails for an invalid owner', function (): void {
    $exit = Artisan::call('options:list', ['--owner' => 'Not\\A\\Model', '--owner-id' => '1']);

    expect($exit)->toBe(1);
});
