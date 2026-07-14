<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\Support\ConfigBridge;
use RoundlyConsulting\Options\Support\OptionAuthorizer;
use RoundlyConsulting\Options\Support\OptionObservers;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\Options\Tests\Settings\AppearanceSettings;

it('registers all publish tags', function (string $tag): void {
    expect(ServiceProvider::pathsToPublish(null, $tag))->not->toBeEmpty();
})->with([
    'options-config',
    'options-migrations',
]);

it('binds the package services as singletons', function (string $service): void {
    expect(app($service))->toBe(app($service));
})->with([
    OptionsManager::class,
    OptionStore::class,
    OptionObservers::class,
    OptionAuthorizer::class,
    ConfigBridge::class,
]);

it('registers the package commands', function (string $command): void {
    expect(array_keys(app(Kernel::class)->all()))->toContain($command);
})->with([
    'make:option',
    'make:option-group',
    'options:list',
    'options:get',
    'options:set',
    'options:clear-cache',
    'options:export',
    'options:import',
]);

it('contributes an options section to about', function (string $expected): void {
    $this->artisan('about --only=options')
        ->expectsOutputToContain($expected)
        ->assertExitCode(0);
})->with([
    'Options',
    'Model',
    'Registry',
    'Groups',
    'Cache',
    'Cache TTL',
    'Events',
    'Authorization',
    'Config overrides',
]);

it('reports registered keys, groups and bridged config paths by count only', function (): void {
    config()->set('options.registry', ['stripe.secret' => ThemeOption::class]);
    config()->set('options.groups', ['billing-secrets' => AppearanceSettings::class]);
    config()->set('options.config_overrides', ['services.stripe.secret' => ThemeOption::class]);
    config()->set('options.cache.store', 'redis-secrets');

    $this->artisan('about --only=options')
        ->doesntExpectOutputToContain('stripe.secret')
        ->doesntExpectOutputToContain('billing-secrets')
        ->doesntExpectOutputToContain('redis-secrets')
        ->assertExitCode(0);
});

it('counts the registered keys in about', function (): void {
    config()->set('options.registry', ['theme' => ThemeOption::class]);

    $this->artisan('about --only=options')
        ->expectsOutputToContain('1 key(s)')
        ->assertExitCode(0);
});

it('reports the cache store as set without naming it', function (): void {
    config()->set('options.cache.store', 'array');

    $this->artisan('about --only=options')
        ->expectsOutputToContain('SET')
        ->assertExitCode(0);
});
