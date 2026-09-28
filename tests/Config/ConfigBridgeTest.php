<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\ConfigBridge;
use RoundlyConsulting\Options\Tests\Options\AdminOnlyOption;
use RoundlyConsulting\Options\Tests\Options\MailFromOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    Options::flushCache();
    app(ConfigBridge::class)->seedFromConfig([]);
});

it('leaves config untouched with an empty map', function (): void {
    config()->set('mail.from.address', 'original@test');

    Options::applyConfigOverrides();

    expect(config('mail.from.address'))->toBe('original@test');
});

it('applies a mapped option that has a stored value', function (): void {
    config()->set('mail.from.address', 'original@test');
    Options::set(MailFromOption::class, 'db@test');

    Options::overrides('mail.from.address', MailFromOption::class);

    expect(config('mail.from.address'))->toBe('db@test');
});

it('does not clobber config when the mapped option has no stored value', function (): void {
    config()->set('mail.from.address', 'original@test');

    Options::overrides('mail.from.address', MailFromOption::class);

    expect(config('mail.from.address'))->toBe('original@test');
});

it('exposes the active override map', function (): void {
    Options::overrides('app.name', ThemeOption::class);

    expect(Options::configOverrides())->toBe(['app.name' => ThemeOption::class]);
});

it('resolves a registry key in overrides', function (): void {
    Options::register(['mail-from' => MailFromOption::class]);
    Options::set('mail-from', 'db@test');

    Options::overrides('mail.from.address', 'mail-from');

    expect(config('mail.from.address'))->toBe('db@test');
});

it('re-applies config on set when live mode is on', function (): void {
    config()->set('options.config_overrides_live', true);
    config()->set('mail.from.address', 'original@test');
    Options::overrides('mail.from.address', MailFromOption::class);

    Options::set(MailFromOption::class, 'live@test');

    expect(config('mail.from.address'))->toBe('live@test');
});

it('reverts to the config value on forget when live mode is on', function (): void {
    config()->set('options.config_overrides_live', true);
    config()->set('mail.from.address', 'original@test');
    Options::set(MailFromOption::class, 'live@test');
    Options::overrides('mail.from.address', MailFromOption::class);

    Options::forget(MailFromOption::class);

    expect(config('mail.from.address'))->toBe('original@test');
});

it('does not change config on set when live mode is off', function (): void {
    config()->set('options.config_overrides_live', false);
    config()->set('mail.from.address', 'original@test');
    Options::overrides('mail.from.address', MailFromOption::class);

    Options::set(MailFromOption::class, 'later@test');

    expect(config('mail.from.address'))->toBe('original@test');
});

it('applies multiple mappings together', function (): void {
    config()->set('mail.from.address', 'm@test');
    config()->set('app.name', 'app');
    Options::set(MailFromOption::class, 'db@test');
    Options::set(ThemeOption::class, 'dark');

    app(ConfigBridge::class)->seedFromConfig([
        'mail.from.address' => MailFromOption::class,
        'app.name' => ThemeOption::class,
    ]);
    Options::applyConfigOverrides();

    expect(config('mail.from.address'))->toBe('db@test')
        ->and(config('app.name'))->toBe('dark');
});

it('does not throw at boot when the options table is absent', function (): void {
    Schema::drop('options');

    config()->set('mail.from.address', 'original@test');

    $bridge = app(ConfigBridge::class);
    $bridge->seedFromConfig(['mail.from.address' => MailFromOption::class]);

    rescue(fn () => $bridge->apply(), report: false);

    expect(config('mail.from.address'))->toBe('original@test');
});

it('reads mapped options without authorization', function (): void {
    Options::withoutAuthorization(fn () => Options::set(AdminOnlyOption::class, 'from-db'));
    config()->set('custom.key', 'env-default');
    config()->set('options.authorization.enabled', true);

    // No user: authorizeRead denies. The bridge is system code and must not care.
    Options::overrides('custom.key', AdminOnlyOption::class);

    expect(config('custom.key'))->toBe('from-db');

    config()->set('custom.key', 'changed');
    Options::applyConfigOverrides();

    expect(config('custom.key'))->toBe('from-db');
});
