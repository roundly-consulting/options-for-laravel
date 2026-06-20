<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->target = app_path('Settings');

    File::deleteDirectory($this->target);
});

afterEach(function (): void {
    File::deleteDirectory($this->target);
});

it('generates an option group class', function (): void {
    $this->artisan('make:option-group', ['name' => 'BillingSettings'])
        ->assertSuccessful();

    $path = $this->target.'/BillingSettings.php';

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)
        ->toContain('class BillingSettings extends OptionGroup')
        ->toContain("return 'Billing Settings';")
        ->toContain('// App\Options\ExampleOption::class,');
});

it('pre-fills options from the --options flag', function (): void {
    $this->artisan('make:option-group', [
        'name' => 'BillingSettings',
        '--options' => 'App\Options\PlanOption,App\Options\SeatsOption',
    ])->assertSuccessful();

    $contents = File::get($this->target.'/BillingSettings.php');

    expect($contents)
        ->toContain('App\Options\PlanOption::class,')
        ->toContain('App\Options\SeatsOption::class,');
});

it('warns instead of overwriting an existing group without --force', function (): void {
    $this->artisan('make:option-group', ['name' => 'BillingSettings'])->assertSuccessful();

    $this->artisan('make:option-group', ['name' => 'BillingSettings'])
        ->expectsOutputToContain('already exists');
});
