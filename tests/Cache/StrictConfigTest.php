<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionsConfig;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * A typo in the host's options cache config fails loudly. Before: a `cache.ttl` of `abc`
 * was cast to 0 — every write expired at once, so the cache silently did nothing — and a
 * non-string store, prefix or tag quietly became the default. A blank value (a host's
 * `KEY=`) is not set and takes the default.
 */
beforeEach(function (): void {
    app(Cache::class)->flush();
    app(OptionStore::class)->flush();
});

it('refuses a junk or non-positive cache ttl (strict config)', function (mixed $ttl): void {
    config()->set('options.cache.ttl', $ttl);

    expect(fn () => Options::set(ThemeOption::class, 'dark'))
        ->toThrow(InvalidConfigurationException::class, 'options.cache.ttl');
})->with(['word' => 'abc', 'decimal' => '1.5', 'zero' => '0', 'negative' => -60, 'bool' => true]);

it('reads a blank cache ttl as not set, caching for the default hour (strict config)', function (string $blank): void {
    config()->set('options.cache.ttl', $blank);

    expect(OptionsConfig::cacheTtl())->toBe(3600);

    Options::set(ThemeOption::class, 'dark');
    app(Cache::class)->flush();

    expect(Options::get(ThemeOption::class))->toBe('dark');
})->with(['empty' => '', 'whitespace' => '  ']);

it('reads a canonical ttl string (strict config)', function (): void {
    config()->set('options.cache.ttl', ' 120 ');

    Options::set(ThemeOption::class, 'dark');
    app(Cache::class)->flush();

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('refuses a non-string cache store, prefix or tag (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => Options::set(ThemeOption::class, 'dark'))
        ->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'array store' => ['options.cache.store', ['array']],
    'int prefix' => ['options.cache.prefix', 7],
    'array tag' => ['options.cache.tag', ['options']],
]);

it('uses the defaults for an unset or blank store, prefix and tag (strict config)', function (?string $unset): void {
    config()->set('options.cache.store', $unset);
    config()->set('options.cache.prefix', $unset);
    config()->set('options.cache.tag', $unset);

    expect(OptionsConfig::cacheStore())->toBeNull()
        ->and(OptionsConfig::cachePrefix())->toBe('options')
        ->and(OptionsConfig::cacheTag())->toBe('options');

    Options::set(ThemeOption::class, 'dark');
    app(Cache::class)->flush();

    expect(Options::get(ThemeOption::class))->toBe('dark');
})->with(['absent' => null, 'empty' => '', 'whitespace' => ' ']);

it('reports a forever ttl as such in about, and refuses junk there too (strict config)', function (): void {
    config()->set('options.cache.ttl', null);

    Artisan::call('about', ['--only' => 'options']);

    expect(Artisan::output())->toContain('FOREVER');

    config()->set('options.cache.ttl', 'abc');

    expect(fn () => Artisan::call('about', ['--only' => 'options']))
        ->toThrow(InvalidConfigurationException::class, 'options.cache.ttl');
});

it('reports a blank cache store as the default in about (strict config)', function (): void {
    config()->set('options.cache.store', '');

    Artisan::call('about', ['--only' => 'options']);

    expect(Artisan::output())->toMatch('/Cache store[ .]*DEFAULT/');
});
