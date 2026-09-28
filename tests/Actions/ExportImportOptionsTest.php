<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
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

    $payloads = Options::export();

    expect($payloads)->toHaveCount(1)
        ->and($payloads[0])->toBeInstanceOf(OptionPayload::class)
        ->and($payloads[0]->key)->toBe('theme')
        ->and($payloads[0]->value)->toBe('dark');
});

it('runs the raw actions directly', function (): void {
    Options::set(ThemeOption::class, 'dark');

    $payloads = app(ExportOptionsAction::class)->execute();
    Option::query()->forceDelete();

    expect(app(ImportOptionsAction::class)->execute($payloads))->toBe(1)
        ->and(Option::query()->value('value'))->toBe('dark');
});

it('round-trips exportJson then import', function (): void {
    Options::set(ThemeOption::class, 'dark');

    $json = Options::exportJson();

    Option::query()->delete();

    expect(json_decode($json, true))->toBe([['key' => 'theme', 'value' => 'dark', 'owner_type' => null, 'owner_id' => null]])
        ->and(Options::import($json))->toBe(1)
        ->and(Options::get(ThemeOption::class))->toBe('dark');
});

it('is idempotent on repeated import', function (): void {
    $json = json_encode([['key' => 'theme', 'value' => 'dark', 'owner_type' => null, 'owner_id' => null]]);

    Options::import($json);
    Options::import($json);

    expect(Option::query()->where('key', 'theme')->count())->toBe(1);
});

it('scopes export to an owner', function (): void {
    $user = User::create();

    Options::for($user)->set(ThemeOption::class, 'dark');
    Options::set(ThemeOption::class, 'global');

    $payloads = Options::export($user);

    expect($payloads)->toHaveCount(1)
        ->and($payloads[0]->value)->toBe('dark')
        ->and($payloads[0]->ownerType)->toBe($user->getMorphClass())
        ->and($payloads[0]->ownerId)->toBe($user->getKey())
        ->and(Options::for($user)->export())->toEqual($payloads)
        ->and(json_decode(Options::exportJson($user), true)[0]['value'])->toBe('dark');
});

it('exports global only when requested', function (): void {
    $user = User::create();

    Options::for($user)->set(ThemeOption::class, 'dark');
    Options::set(ThemeOption::class, 'global');

    expect(Options::export(globalOnly: true))->toHaveCount(1)
        ->and(Options::export(globalOnly: true)[0]->value)->toBe('global')
        ->and(Options::for(null)->export())->toEqual(Options::export(globalOnly: true))
        ->and(Options::export())->toHaveCount(2);
});

it('imports rows, payloads and owned rows', function (): void {
    $user = User::create();

    $count = Options::import([
        ['key' => 'theme', 'value' => 'dark'],
        new OptionPayload('theme', 'light', $user->getMorphClass(), $user->getKey()),
        ['key' => 'locale', 'value' => 'sk', 'owner_type' => $user->getMorphClass(), 'owner_id' => (string) $user->getKey()],
    ]);

    expect($count)->toBe(3)
        ->and(Options::get(ThemeOption::class))->toBe('dark')
        ->and(Options::for($user)->get(ThemeOption::class))->toBe('light')
        ->and(Option::query()->where('key', 'locale')->value('owner_id'))->toBe($user->getKey());
});

it('rejects malformed payloads', function (array|string $payload): void {
    Options::import($payload);
})->throws(InvalidOptionPayload::class)->with([
    'malformed json' => ['{not json'],
    'json scalar' => ['"a string"'],
    'row without key' => [[['value' => 'x']]],
    'scalar row' => [['nope']],
]);

it('drops the persistent cache of every imported value', function (): void {
    // Regression: import flushed only the in-request memo, so a cached value
    // kept being served after the import until the persistent TTL ran out.
    config()->set('options.cache.enabled', true);
    Options::set(ThemeOption::class, 'dark');
    expect(Options::get(ThemeOption::class))->toBe('dark');

    Options::import([['key' => 'theme', 'value' => 'light']]);

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('keeps string owner ids of uuid/ulid owners', function (): void {
    // Regression: OptionPayload::$ownerId was ?int — exporting a uuid-owned row threw a
    // TypeError and importing one dropped the id, turning an owned option into a global one.
    config()->set('options.key_type', 'uuid');
    Schema::dropIfExists('options');
    (require __DIR__.'/../../database/migrations/create_options_table.php')->up();
    $uuid = '0199a2b4-7c1e-7d3a-9f0e-5a4b3c2d1e0f';
    Option::query()->create(['key' => 'theme', 'value' => 'dark', 'owner_type' => 'team', 'owner_id' => $uuid]);

    $exported = Options::export();
    $json = Options::exportJson();
    Option::query()->forceDelete();
    Options::import($json);

    expect($exported[0]->ownerId)->toBe($uuid)
        ->and(Option::query()->whereNull('owner_id')->count())->toBe(0)
        ->and(Option::query()->value('owner_id'))->toBe($uuid);
});

it('exposes payload as array', function (): void {
    $payload = new OptionPayload('theme', 'dark', 'App\\User', 7);

    expect($payload->toArray())->toBe([
        'key' => 'theme',
        'value' => 'dark',
        'owner_type' => 'App\\User',
        'owner_id' => 7,
    ])->and(OptionPayload::fromArray(['key' => 'k', 'owner_type' => '', 'owner_id' => '12']))->toEqual(new OptionPayload('k', null))
        ->and(OptionPayload::fromArray(['key' => 'k', 'owner_type' => 't', 'owner_id' => '12'])->ownerId)->toBe(12)
        ->and(OptionPayload::fromArray(['key' => 'k', 'owner_type' => 't', 'owner_id' => 12])->ownerId)->toBe(12)
        ->and(OptionPayload::fromArray(['key' => 'k', 'owner_type' => 't', 'owner_id' => ''])->ownerId)->toBeNull();
});
