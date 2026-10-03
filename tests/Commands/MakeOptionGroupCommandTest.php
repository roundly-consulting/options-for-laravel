<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Tests\Options\LocaleOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

/*
 * The generator writes into app/Settings: a throwaway app/ per test, never the shared testbench
 * skeleton every parallel process boots from. The app path is read when the command runs, so
 * pointing it here is enough. The namespace is resolved first — Laravel derives it by
 * matching app/ against the skeleton's composer.json, which a sandbox would not match.
 */
beforeEach(function (): void {
    $this->app->getNamespace();
    $this->app->useAppPath($this->sandbox = sys_get_temp_dir().'/options-make-group-'.bin2hex(random_bytes(6)));

    $this->target = app_path('Settings');
});

afterEach(fn () => File::deleteDirectory($this->sandbox));

it('generates into the sandbox, never the shared skeleton', function (): void {
    expect($this->target)->toContain('options-make-group-');
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
        ->toContain('// \App\Options\ExampleOption::class,');
});

it('pre-fills options from the --options flag', function (): void {
    $this->artisan('make:option-group', [
        'name' => 'BillingSettings',
        '--options' => 'App\Options\PlanOption,App\Options\SeatsOption',
    ])->assertSuccessful();

    $contents = File::get($this->target.'/BillingSettings.php');

    expect($contents)
        ->toContain('\App\Options\PlanOption::class,')
        ->toContain('\App\Options\SeatsOption::class,');
});

it('generates a group whose options resolve from inside its namespace', function (): void {
    $class = 'ResolvingSettings'.Str::random(8);

    $this->artisan('make:option-group', [
        'name' => $class,
        '--options' => ThemeOption::class.', \\'.LocaleOption::class,
    ])->assertSuccessful();

    require $this->target.'/'.$class.'.php';

    $group = app('App\\Settings\\'.$class);

    expect($group->options())->toBe([ThemeOption::class, LocaleOption::class])
        ->and(Options::group($group)->all())->toBe(['theme' => 'light', 'locale' => 'en']);
});

it('warns instead of overwriting an existing group without --force', function (): void {
    $this->artisan('make:option-group', ['name' => 'BillingSettings'])->assertSuccessful();

    $this->artisan('make:option-group', ['name' => 'BillingSettings'])
        ->expectsOutputToContain('already exists');
});
