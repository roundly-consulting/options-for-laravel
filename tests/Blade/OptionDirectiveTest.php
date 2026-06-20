<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    Cache::getInstance()->flush();
    Options::register(['theme' => ThemeOption::class]);
});

it('renders an option value by key', function (): void {
    Options::set(ThemeOption::class, 'dark');

    expect(Blade::render("@option('theme')"))->toBe('dark');
});

it('renders an option value by class-string', function (): void {
    Options::set(ThemeOption::class, 'dark');

    expect(Blade::render('@option($class)', ['class' => ThemeOption::class]))->toBe('dark');
});

it('escapes the rendered value', function (): void {
    Options::set(ThemeOption::class, '<script>');

    expect(Blade::render("@option('theme')"))->toBe('&lt;script&gt;');
});
