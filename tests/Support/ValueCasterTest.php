<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Options\Casts\EnumCast;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\ValueCaster;
use RoundlyConsulting\Options\Tests\Options\Status;

enum ValueCasterPureEnum
{
    case Alpha;
}

it('serializes a value to the string the database would hand back', function (mixed $value, string $expected): void {
    expect(ValueCaster::serialize(new Option, 'value', 'string', $value))->toBe($expected);
})->with([
    'string' => ['dark', 'dark'],
    'int' => [5, '5'],
    'float' => [1.5, '1.5'],
    'false' => [false, '0'],
    'true' => [true, '1'],
    'backed enum' => [Status::Active, 'active'],
    'pure enum' => [ValueCasterPureEnum::Alpha, 'Alpha'],
    'date' => [CarbonImmutable::parse('2026-01-02 03:04:05'), '2026-01-02 03:04:05'],
    'stringable' => [str('hello'), 'hello'],
    'array' => [['a' => 1], '{"a":1}'],
]);

it('keeps null as null', function (): void {
    expect(ValueCaster::serialize(new Option, 'value', 'integer', null))->toBeNull();
});

it('round-trips through a cast instance without Stringable', function (): void {
    $cast = new EnumCast(Status::class);

    $raw = ValueCaster::serialize(new Option, 'value', $cast, Status::Active);

    expect($raw)->toBe('active')
        ->and(ValueCaster::hydrate(new Option, 'value', $cast, $raw))->toBe(Status::Active);
});

it('hydrates a raw string through a cast string', function (): void {
    expect(ValueCaster::hydrate(new Option, 'value', 'integer', '42'))->toBe(42)
        ->and(ValueCaster::hydrate(new Option, 'value', 'collection', '{"a":1}')?->all())->toBe(['a' => 1])
        ->and(ValueCaster::hydrate(new Option, 'value', 'integer', null))->toBeNull();
});
