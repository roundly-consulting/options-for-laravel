<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionModel;
use RoundlyConsulting\Options\Tests\Models\CustomOption;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

beforeEach(function (): void {
    app(Cache::class)->flush();
});

it('resolves the packaged model by default', function (): void {
    expect(OptionModel::class())->toBe(Option::class);
});

it('resolves a host model configured on options.model', function (): void {
    config()->set('options.model', CustomOption::class);

    expect(OptionModel::class())->toBe(CustomOption::class);
});

it('refuses a foreign model instead of falling back to the packaged one', function (): void {
    // The toolkit refuses any class that is not the packaged model or a subclass of it.
    config()->set('options.model', User::class);

    expect(fn (): string => OptionModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [options.model] must be a class-string of ['.Option::class.'], ['.User::class.'] given.',
    );
});

it('reads and writes through the configured host model', function (): void {
    config()->set('options.model', CustomOption::class);

    Options::set(ThemeOption::class, 'dark');

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(CustomOption::query()->where('key', 'theme')->exists())->toBeTrue();
});

it('exports and imports through the configured host model', function (): void {
    config()->set('options.model', CustomOption::class);

    Options::set(ThemeOption::class, 'dark');

    $json = Options::exportJson();

    CustomOption::query()->forceDelete();

    expect(Options::import($json))->toBe(1)
        ->and(CustomOption::query()->count())->toBe(1);
});
