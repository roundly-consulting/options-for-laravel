<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    app(Cache::class)->flush();
    // A throwaway file per test, never the shared testbench skeleton's storage/.
    $this->path = sys_get_temp_dir().'/options-export-'.bin2hex(random_bytes(6)).'.json';
});

afterEach(fn () => File::delete($this->path));

it('exports options to stdout', function (): void {
    Options::set(ThemeOption::class, 'dark');

    $this->artisan('options:export')
        ->expectsOutputToContain('theme')
        ->assertSuccessful();
});

it('exports options to a file and re-imports them', function (): void {
    Options::set(ThemeOption::class, 'dark');

    $this->artisan('options:export', ['--path' => $this->path])
        ->assertSuccessful();

    expect(File::exists($this->path))->toBeTrue();

    Option::query()->delete();

    $this->artisan('options:import', ['path' => $this->path])
        ->expectsOutputToContain('Imported 1')
        ->assertSuccessful();

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('fails to import a missing file', function (): void {
    $this->artisan('options:import', ['path' => '/no/such/file.json'])
        ->assertFailed();
});
