<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\AdminOnlyOption;
use RoundlyConsulting\Options\Tests\Options\DeniedOption;

beforeEach(function (): void {
    Options::flushCache();
    config()->set('options.authorization.enabled', true);
    config()->set('auth.providers.users.model', User::class);
});

it('bypasses authorization by default in the set command', function (): void {
    $this->artisan('options:set', ['option' => AdminOnlyOption::class, 'value' => 'cli'])
        ->assertSuccessful();
});

it('bypasses authorization by default in the get command', function (): void {
    Options::withoutAuthorization(fn () => Options::set(AdminOnlyOption::class, 'cli'));

    $this->artisan('options:get', ['option' => AdminOnlyOption::class])
        ->expectsOutput('cli')
        ->assertSuccessful();
});

it('respects the acl when run with --as for an authorized user', function (): void {
    $user = User::query()->create();

    $this->artisan('options:set', [
        'option' => AdminOnlyOption::class,
        'value' => 'ok',
        '--as' => (string) $user->getKey(),
    ])->assertSuccessful();
});

it('fails with --as when the user is unknown', function (): void {
    $this->artisan('options:set', [
        'option' => AdminOnlyOption::class,
        'value' => 'nope',
        '--as' => '999',
    ])->assertFailed();
});

it('fails with --as when a real user is denied by the option', function (): void {
    $user = User::query()->create();

    $this->artisan('options:set', [
        'option' => DeniedOption::class,
        'value' => 'nope',
        '--as' => (string) $user->getKey(),
    ])->assertFailed();
});

it('bypasses authorization by default in the list command', function (): void {
    Options::withoutAuthorization(fn () => Options::set(AdminOnlyOption::class, 'secret-admin-value'));
    Options::register(['admin-only' => AdminOnlyOption::class]);

    $this->artisan('options:list')
        ->expectsOutputToContain('secret-admin-value')
        ->assertSuccessful();
});

it('bypasses authorization in the export and import commands', function (): void {
    // C-6: export()/import() enforce authorization now; the commands are system code.
    Options::withoutAuthorization(fn () => Options::set(AdminOnlyOption::class, 'secret-admin-value'));
    Options::register(['admin-only' => AdminOnlyOption::class]);
    $path = sys_get_temp_dir().'/options-auth-export-'.bin2hex(random_bytes(6)).'.json';

    try {
        $this->artisan('options:export')
            ->expectsOutputToContain('secret-admin-value')
            ->assertSuccessful();

        File::put($path, json_encode([['key' => 'admin-only', 'value' => 'from-cli']]));

        $this->artisan('options:import', ['path' => $path])->assertSuccessful();
    } finally {
        File::delete($path);
    }

    expect(Options::withoutAuthorization(fn () => Options::get(AdminOnlyOption::class)))->toBe('from-cli');
});
