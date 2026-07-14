<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Actions\ExportOptionsAction;
use RoundlyConsulting\Options\Actions\ImportOptionsAction;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionModel;
use RoundlyConsulting\Options\Tests\Models\CustomOption;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    Cache::getInstance()->flush();
});

it('resolves the packaged model by default', function (): void {
    expect(OptionModel::class())->toBe(Option::class);
});

it('resolves a host model configured on options.model', function (): void {
    config()->set('options.model', CustomOption::class);

    expect(OptionModel::class())->toBe(CustomOption::class);
});

it('falls back to the packaged model when the configured class is not an option', function (): void {
    config()->set('options.model', User::class);

    expect(OptionModel::class())->toBe(Option::class);
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

    $json = app(ExportOptionsAction::class)->toJson();

    CustomOption::query()->forceDelete();

    expect(app(ImportOptionsAction::class)->fromJson($json))->toBe(1)
        ->and(CustomOption::query()->count())->toBe(1);
});
