<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * A typo in the host's options cache config fails loudly. Before: a `cache.ttl` of `abc`
 * was cast to 0 — every write expired at once, so the cache silently did nothing — and a
 * non-string store, prefix or tag quietly became the default.
 */
beforeEach(function (): void {
    app(Cache::class)->flush();
    app(OptionStore::class)->flush();
});

it('refuses a junk or non-positive cache ttl (strict config)', function (mixed $ttl): void {
    config()->set('options.cache.ttl', $ttl);

    expect(fn () => Options::set(ThemeOption::class, 'dark'))
        ->toThrow(InvalidConfigurationException::class, 'options.cache.ttl');
})->with(['word' => 'abc', 'decimal' => '1.5', 'blank' => '', 'zero' => '0', 'negative' => -60, 'bool' => true]);

it('reads a canonical ttl string (strict config)', function (): void {
    config()->set('options.cache.ttl', ' 120 ');

    Options::set(ThemeOption::class, 'dark');
    app(Cache::class)->flush();

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('refuses a blank or non-string cache store, prefix or tag (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => Options::set(ThemeOption::class, 'dark'))
        ->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'blank store' => ['options.cache.store', ''],
    'array store' => ['options.cache.store', ['array']],
    'blank prefix' => ['options.cache.prefix', ''],
    'int prefix' => ['options.cache.prefix', 7],
    'blank tag' => ['options.cache.tag', ' '],
    'array tag' => ['options.cache.tag', ['options']],
]);

it('uses the defaults for an unset store, prefix and tag (strict config)', function (): void {
    config()->set('options.cache.store', null);
    config()->set('options.cache.prefix', null);
    config()->set('options.cache.tag', null);

    Options::set(ThemeOption::class, 'dark');
    app(Cache::class)->flush();

    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('reports a forever ttl as such in about, and refuses junk there too (strict config)', function (): void {
    config()->set('options.cache.ttl', null);

    Artisan::call('about', ['--only' => 'options']);

    expect(Artisan::output())->toContain('FOREVER');

    config()->set('options.cache.ttl', 'abc');

    expect(fn () => Artisan::call('about', ['--only' => 'options']))
        ->toThrow(InvalidConfigurationException::class, 'options.cache.ttl');
});
