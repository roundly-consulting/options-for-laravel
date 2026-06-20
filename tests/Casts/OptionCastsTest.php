<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\ArrayOption;
use RoundlyConsulting\Options\Tests\Options\DateOption;

beforeEach(fn () => Cache::getInstance()->flush());

it('round-trips a date option', function (): void {
    $when = CarbonImmutable::parse('2026-06-20 12:00:00');

    DateOption::make()->set($when);

    expect(DateOption::make()->value())
        ->toBeInstanceOf(CarbonImmutable::class)
        ->format('Y-m-d H:i:s')->toBe('2026-06-20 12:00:00');
});

it('round-trips an array option', function (): void {
    ArrayOption::make()->set(['a' => 1, 'b' => 2]);

    expect(ArrayOption::make()->value())->toBe(['a' => 1, 'b' => 2]);
});

it('returns the array default when unset', function (): void {
    expect(ArrayOption::make()->value())->toBe([]);
});
