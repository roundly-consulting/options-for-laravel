<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => app(Cache::class)->flush());

it('clears the option caches', function (): void {
    Options::set(ThemeOption::class, 'dark');

    $this->artisan('options:clear-cache')
        ->expectsOutputToContain('Option caches cleared')
        ->assertSuccessful();

    DB::enableQueryLog();
    Options::get(ThemeOption::class);

    expect(DB::getQueryLog())->not->toHaveCount(0);

    DB::disableQueryLog();
});

it('clears the persistent cache on a store without tags', function (): void {
    $directory = useFileOptionCache();

    try {
        Options::set(ThemeOption::class, 'dark');
        DB::table('options')->where('key', 'theme')->update(['value' => 'light-edited']);

        $this->artisan('options:clear-cache')->assertSuccessful();

        expect(Options::get(ThemeOption::class))->toBe('light-edited');

        // Another process: its own memo, the same file store.
        app()->forgetScopedInstances();

        expect(Options::get(ThemeOption::class))->toBe('light-edited');
    } finally {
        File::deleteDirectory($directory);
    }
});
