<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Options\Exceptions\UnauthorizedOption;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Groups\OptionGroup;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Tests\Options\AdminOnlyOption;
use RoundlyConsulting\Options\Tests\Options\AgeOption;
use RoundlyConsulting\Options\Tests\Options\LocaleOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => Options::flushCache());

function themeAndAgeGroup(): OptionGroup
{
    return new class extends OptionGroup
    {
        public function options(): array
        {
            return [ThemeOption::class, AgeOption::class];
        }
    };
}

it('writes nothing from a group when one value fails validation', function (): void {
    expect(fn () => Options::group(themeAndAgeGroup())->set(['theme' => 'dark', 'age' => 'abc']))
        ->toThrow(ValidationException::class);

    expect(Options::has(ThemeOption::class))->toBeFalse()
        ->and(Options::get(ThemeOption::class))->toBe('light');
});

it('writes nothing from setMany when one value fails validation', function (): void {
    expect(fn () => Options::setMany([ThemeOption::class => 'dark', AgeOption::class => 'abc']))
        ->toThrow(ValidationException::class);

    expect(Option::query()->count())->toBe(0);
});

it('writes nothing from setMany when one option is not writable', function (): void {
    config()->set('options.authorization.enabled', true);

    expect(fn () => Options::setMany([ThemeOption::class => 'dark', AdminOnlyOption::class => 'x']))
        ->toThrow(UnauthorizedOption::class);

    expect(Option::query()->count())->toBe(0);
});

it('rolls a batch back, caches included, when a write fails midway', function (): void {
    Options::set(ThemeOption::class, 'light');

    Option::saving(function (Option $option): void {
        if ($option->key === 'locale') {
            throw new RuntimeException('disk full');
        }
    });

    expect(fn () => Options::setMany([ThemeOption::class => 'dark', LocaleOption::class => 'sk']))
        ->toThrow(RuntimeException::class, 'disk full');

    expect(Option::query()->where('key', 'theme')->value('value'))->toBe('light')
        ->and(Options::get(ThemeOption::class))->toBe('light');
});

it('writes every value when all of them are valid', function (): void {
    Options::group(themeAndAgeGroup())->set(['theme' => 'dark', AgeOption::class => '30']);

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(Options::get(AgeOption::class))->toBe('30');
});

it('validates the whole batch first under the fake too', function (): void {
    $fake = Options::fake();

    expect(fn () => Options::group(themeAndAgeGroup())->set(['theme' => 'dark', 'age' => 'abc']))
        ->toThrow(ValidationException::class);

    $fake->assertNothingSet();
});
