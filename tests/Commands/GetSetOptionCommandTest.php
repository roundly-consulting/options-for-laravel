<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ArrayOption;
use RoundlyConsulting\Options\Tests\Options\FlagOption;
use RoundlyConsulting\Options\Tests\Options\SimpleOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    app(Cache::class)->flush();
    Options::register(['theme' => ThemeOption::class]);
});

it('round-trips a global option through the cli', function (): void {
    $this->artisan('options:set', ['option' => 'theme', 'value' => 'dark'])
        ->assertSuccessful();

    app(Cache::class)->flush();

    $this->artisan('options:get', ['option' => 'theme'])
        ->expectsOutput('dark')
        ->assertSuccessful();
});

it('round-trips a scoped option through the cli', function (): void {
    $user = User::create();

    $this->artisan('options:set', [
        'option' => ThemeOption::class,
        'value' => 'dark',
        '--owner' => User::class,
        '--owner-id' => (string) $user->getKey(),
    ])->assertSuccessful();

    app(Cache::class)->flush();

    $this->artisan('options:get', [
        'option' => ThemeOption::class,
        '--owner' => User::class,
        '--owner-id' => (string) $user->getKey(),
    ])->expectsOutput('dark')->assertSuccessful();
});

it('decodes a json value when flagged', function (): void {
    $this->artisan('options:set', [
        'option' => ArrayOption::class,
        'value' => '{"a":1}',
        '--json' => true,
    ])->assertSuccessful();

    expect(Options::get(ArrayOption::class))->toBe(['a' => 1]);
});

it('fails for an invalid owner class', function (): void {
    $this->artisan('options:set', [
        'option' => 'theme',
        'value' => 'dark',
        '--owner' => 'Not\\A\\Model',
        '--owner-id' => '1',
    ])->assertFailed();
});

it('fails when owner id is missing', function (): void {
    $this->artisan('options:get', [
        'option' => 'theme',
        '--owner' => User::class,
    ])->assertFailed();
});

it('fails for an unknown option', function (): void {
    $this->artisan('options:get', ['option' => 'nope'])->assertFailed();
});

it('prints an empty line for a null value', function (): void {
    $this->artisan('options:get', ['option' => SimpleOption::class])
        ->expectsOutput('')
        ->assertSuccessful();
});

it('prints a json encoded array value', function (): void {
    Options::set(ArrayOption::class, ['a' => 1]);

    $this->artisan('options:get', ['option' => ArrayOption::class])
        ->expectsOutput('{"a":1}')
        ->assertSuccessful();
});

it('prints a boolean value as a word', function (): void {
    Options::flushCache();

    $this->artisan('options:get', ['option' => FlagOption::class])
        ->expectsOutput('false')
        ->assertSuccessful();
});

it('prints a numeric value', function (): void {
    Options::set(FlagOption::class, 1);
    Options::flushCache();

    $this->artisan('options:get', ['option' => FlagOption::class])
        ->expectsOutput('true')
        ->assertSuccessful();
});
