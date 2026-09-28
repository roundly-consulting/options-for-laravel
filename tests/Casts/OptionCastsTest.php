<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\ArrayOption;
use RoundlyConsulting\Options\Tests\Options\DateOption;
use RoundlyConsulting\Options\Tests\Options\InstanceCastOption;
use RoundlyConsulting\Options\Tests\Options\Status;

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

it('round-trips an option whose castAs is a cast instance', function (): void {
    InstanceCastOption::make()->set('active');

    expect(InstanceCastOption::make()->value())->toBe(Status::Active)
        ->and(Option::query()->where('key', 'instance-cast')->value('value'))->toBe('active');

    Options::flushCache();

    expect(InstanceCastOption::make()->value())->toBe(Status::Active);
});
