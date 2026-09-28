<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\AgeOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => app(Cache::class)->flush());

it('accepts a valid value', function (): void {
    AgeOption::make()->set(5);

    Options::flushCache();

    expect(AgeOption::make()->value())->toBe('5');
});

it('rejects an invalid value', function (): void {
    AgeOption::make()->set('abc');
})->throws(ValidationException::class);

it('rejects an out-of-range value', function (): void {
    AgeOption::make()->set(-1);
})->throws(ValidationException::class);

it('skips validation when rules are empty', function (): void {
    ThemeOption::make()->set('anything');

    expect(ThemeOption::make()->value())->toBe('anything');
});
