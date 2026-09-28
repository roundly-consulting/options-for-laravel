<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RoundlyConsulting\Options\Casts\EncryptedCast;
use RoundlyConsulting\Options\Casts\EnumCast;
use RoundlyConsulting\Options\Exceptions\EncryptionNotSupported;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\EncryptedCollectionOption;
use RoundlyConsulting\Options\Tests\Options\EncryptedEnumOption;
use RoundlyConsulting\Options\Tests\Options\EncryptedInstanceCastOption;
use RoundlyConsulting\Options\Tests\Options\EncryptedIntegerOption;
use RoundlyConsulting\Options\Tests\Options\SecretOption;
use RoundlyConsulting\Options\Tests\Options\Status;

beforeEach(fn () => Cache::getInstance()->flush());

it('stores ciphertext at rest and decrypts on read', function (): void {
    SecretOption::make()->set('top-secret');

    $stored = Option::query()->where('key', 'secret')->value('value');

    expect($stored)->not->toBe('top-secret')
        ->and(Crypt::decryptString($stored))->toBe('top-secret')
        ->and(SecretOption::make()->value())->toBe('top-secret');
});

it('returns the default when an encrypted option is unset', function (): void {
    expect(SecretOption::make()->value())->toBeNull();
});

it('encrypts non-string casts like collections', function (): void {
    EncryptedCollectionOption::make()->set(collect(['a' => 1]));

    $stored = Option::query()->where('key', 'encrypted-collection')->value('value');

    expect($stored)->not->toContain('"a"')
        ->and(Crypt::decryptString($stored))->toBe('{"a":1}')
        ->and(EncryptedCollectionOption::make()->value()->toArray())->toBe(['a' => 1]);
});

it('returns null on null get and set', function (): void {
    $cast = new EncryptedCast;
    $model = new Option;

    expect($cast->get($model, 'value', null, []))->toBeNull()
        ->and($cast->set($model, 'value', null, []))->toBeNull();
});

it('throws when decrypting tampered ciphertext', function (): void {
    $cast = new EncryptedCast;
    $model = new Option;

    $cast->get($model, 'value', 'not-real-ciphertext', []);
})->throws(DecryptException::class);

it('round-trips an array cast through the encrypted cast', function (): void {
    $cast = new EncryptedCast('array');
    $model = new Option;

    $stored = $cast->set($model, 'value', ['a' => 1], []);

    expect(Crypt::decryptString($stored))->toBe('{"a":1}')
        ->and($cast->get($model, 'value', $stored, []))->toBe(['a' => 1]);
});

it('round-trips a json cast through the encrypted cast', function (): void {
    $cast = new EncryptedCast('json');
    $model = new Option;

    $stored = $cast->set($model, 'value', ['b' => 2], []);

    expect($cast->get($model, 'value', $stored, []))->toBe(['b' => 2]);
});

it('serializes a backed enum value', function (): void {
    $cast = new EncryptedCast('string');
    $model = new Option;

    $stored = $cast->set($model, 'value', Status::Active, []);

    expect(Crypt::decryptString($stored))->toBe('active')
        ->and($cast->get($model, 'value', $stored, []))->toBe('active');
});

it('json-encodes a non-scalar value for a string cast', function (): void {
    $cast = new EncryptedCast('string');
    $model = new Option;

    $stored = $cast->set($model, 'value', ['nested' => true], []);

    expect(Crypt::decryptString($stored))->toBe('{"nested":true}');
});

it('delegates to a custom inner cast class', function (): void {
    $cast = new EncryptedCast(EnumCast::class.':'.Status::class);
    $model = new Option;

    $stored = $cast->set($model, 'value', Status::Active, []);

    expect(Crypt::decryptString($stored))->toBe('active')
        ->and($cast->get($model, 'value', $stored, []))->toBe(Status::Active);
});

it('delegates to a custom inner cast instance', function (): void {
    $cast = new EncryptedCast(new EnumCast(Status::class));
    $model = new Option;

    $stored = $cast->set($model, 'value', Status::Inactive, []);

    expect($cast->get($model, 'value', $stored, []))->toBe(Status::Inactive);
});

it('returns null when a custom inner cast serializes to null', function (): void {
    $cast = new EncryptedCast(new EnumCast(Status::class));
    $model = new Option;

    expect($cast->set($model, 'value', null, []))->toBeNull();
});

it('encrypts an option that casts to an enum via a class-string', function (): void {
    EncryptedEnumOption::make()->set(Status::Active);

    $stored = Option::query()->where('key', 'encrypted-enum')->value('value');

    expect(Crypt::decryptString($stored))->toBe('active');

    Cache::getInstance()->flush();

    expect(EncryptedEnumOption::make()->value())->toBe(Status::Active);
});

it('rejects encrypting an option whose castAs is an instance', function (): void {
    EncryptedInstanceCastOption::make()->set(Status::Active);
})->throws(EncryptionNotSupported::class);

it('rehydrates an encrypted integer option read from the database', function (): void {
    EncryptedIntegerOption::make()->set(42);

    Options::flushCache();

    expect(EncryptedIntegerOption::make()->value())->toBe(42);
});

it('rehydrates scalar inner casts', function (string $inner, mixed $value, mixed $expected): void {
    $cast = new EncryptedCast($inner);
    $model = new Option;

    $stored = $cast->set($model, 'value', $value, []);

    expect($cast->get($model, 'value', $stored, []))->toBe($expected);
})->with([
    'integer' => ['integer', 42, 42],
    'integer from a string' => ['integer', '7', 7],
    'boolean false' => ['boolean', false, false],
    'boolean true' => ['boolean', true, true],
    'float' => ['float', 1.5, 1.5],
    'native enum' => [Status::class, Status::Active, Status::Active],
]);

it('rehydrates a datetime inner cast', function (): void {
    $cast = new EncryptedCast('immutable_datetime');
    $model = new Option;

    $stored = $cast->set($model, 'value', CarbonImmutable::parse('2026-01-02 03:04:05'), []);

    expect(Crypt::decryptString($stored))->toBe('2026-01-02 03:04:05')
        ->and($cast->get($model, 'value', $stored, [])?->toDateTimeString())->toBe('2026-01-02 03:04:05');
});
