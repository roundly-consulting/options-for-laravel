<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Casts\EnumCast;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\Status;
use RoundlyConsulting\Options\Tests\Options\StatusOption;

beforeEach(fn () => Cache::getInstance()->flush());

it('casts a stored value to its enum', function (): void {
    StatusOption::make()->set(Status::Active);

    expect(StatusOption::make()->value())->toBe(Status::Active);
});

it('stores the scalar enum value at rest', function (): void {
    StatusOption::make()->set(Status::Active);

    $this->assertDatabaseHas('options', ['key' => 'status', 'value' => 'active']);
});

it('returns the default when unset', function (): void {
    expect(StatusOption::make()->value())->toBe(Status::Inactive);
});

it('passes through an enum on set', function (): void {
    $cast = new EnumCast(Status::class);
    $model = new Option;

    expect($cast->set($model, 'value', Status::Active, []))->toBe('active')
        ->and($cast->set($model, 'value', null, []))->toBeNull()
        ->and($cast->set($model, 'value', 'inactive', []))->toBe('inactive');
});

it('returns null for empty values on get', function (): void {
    $cast = new EnumCast(Status::class);
    $model = new Option;

    expect($cast->get($model, 'value', null, []))->toBeNull()
        ->and($cast->get($model, 'value', '', []))->toBeNull()
        ->and($cast->get($model, 'value', Status::Active, []))->toBe(Status::Active);
});

it('tries from on an invalid stored value', function (): void {
    $cast = new EnumCast(Status::class);
    $model = new Option;

    expect($cast->get($model, 'value', 'bogus', []))->toBeNull();
});
