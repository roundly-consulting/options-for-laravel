<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Actions\ExportOptionsAction;
use RoundlyConsulting\Options\Actions\ImportOptionsAction;
use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Exceptions\InvalidOptionPayload;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => Cache::getInstance()->flush());

it('exports stored options as payloads', function (): void {
    Options::set(ThemeOption::class, 'dark');

    $payloads = app(ExportOptionsAction::class)->execute();

    expect($payloads)->toHaveCount(1)
        ->and($payloads[0])->toBeInstanceOf(OptionPayload::class)
        ->and($payloads[0]->key)->toBe('theme')
        ->and($payloads[0]->value)->toBe('dark');
});

it('round-trips export then import', function (): void {
    Options::set(ThemeOption::class, 'dark');

    $json = app(ExportOptionsAction::class)->toJson();

    Option::query()->delete();

    $count = app(ImportOptionsAction::class)->fromJson($json);

    expect($count)->toBe(1)
        ->and(Options::get(ThemeOption::class))->toBe('dark');
});

it('is idempotent on repeated import', function (): void {
    $json = json_encode([['key' => 'theme', 'value' => 'dark', 'owner_type' => null, 'owner_id' => null]]);

    app(ImportOptionsAction::class)->fromJson($json);
    app(ImportOptionsAction::class)->fromJson($json);

    expect(Option::query()->where('key', 'theme')->count())->toBe(1);
});

it('scopes export to an owner', function (): void {
    $user = User::create();

    Options::for($user)->set(ThemeOption::class, 'dark');
    Options::set(ThemeOption::class, 'global');

    $payloads = app(ExportOptionsAction::class)->execute($user);

    expect($payloads)->toHaveCount(1)
        ->and($payloads[0]->value)->toBe('dark');
});

it('exports global only when requested', function (): void {
    $user = User::create();

    Options::for($user)->set(ThemeOption::class, 'dark');
    Options::set(ThemeOption::class, 'global');

    $payloads = app(ExportOptionsAction::class)->execute(null, true);

    expect($payloads)->toHaveCount(1)
        ->and($payloads[0]->value)->toBe('global');
});

it('imports from an array', function (): void {
    $count = app(ImportOptionsAction::class)->fromArray([
        ['key' => 'theme', 'value' => 'dark'],
    ]);

    expect($count)->toBe(1)
        ->and(Options::get(ThemeOption::class))->toBe('dark');
});

it('throws on malformed json', function (): void {
    app(ImportOptionsAction::class)->fromJson('{not json');
})->throws(InvalidOptionPayload::class);

it('throws when json is not an array', function (): void {
    app(ImportOptionsAction::class)->fromJson('"a string"');
})->throws(InvalidOptionPayload::class);

it('throws when a row is missing a key', function (): void {
    app(ImportOptionsAction::class)->fromArray([['value' => 'x']]);
})->throws(InvalidOptionPayload::class);

it('exposes payload as array', function (): void {
    $payload = new OptionPayload('theme', 'dark', 'App\\User', 7);

    expect($payload->toArray())->toBe([
        'key' => 'theme',
        'value' => 'dark',
        'owner_type' => 'App\\User',
        'owner_id' => 7,
    ]);
});
