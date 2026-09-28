<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
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
