<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Options\Events\OptionResolved;
use RoundlyConsulting\Options\Events\OptionSet;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\OptionAuthorizer;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Every switch arrives from `.env` as a string; `off` / `no` must switch it off,
 * not count as a truthy non-empty string.
 */
it('reads env-string switches as booleans', function (string $value, bool $expected): void {
    config()->set('options.cache.enabled', $value);
    config()->set('options.authorization.enabled', $value);

    expect(app(OptionStore::class)->isEnabled())->toBe($expected)
        ->and(app(OptionAuthorizer::class)->enforcing())->toBe($expected);
})->with([
    'off' => ['off', false],
    'no' => ['no', false],
    '0' => ['0', false],
    'false' => ['false', false],
    'on' => ['on', true],
    'yes' => ['yes', true],
    '1' => ['1', true],
]);

it('reads env-string event switches as booleans', function (): void {
    Event::fake([OptionSet::class, OptionResolved::class]);
    config()->set('options.events.enabled', 'off');

    Options::set(ThemeOption::class, 'dark');

    Event::assertNotDispatched(OptionSet::class);

    config()->set('options.events.enabled', 'on');
    config()->set('options.events.resolved', 'no');
    Options::get(ThemeOption::class);

    Event::assertNotDispatched(OptionResolved::class);
});

it('throws on a switch typo instead of reading it as the default (strict config)', function (string $key, Closure $read): void {
    config()->set($key, 'disabled');

    expect($read)->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.");
})->with([
    'cache' => ['options.cache.enabled', fn (): bool => app(OptionStore::class)->isEnabled()],
    'authorization' => ['options.authorization.enabled', fn (): bool => app(OptionAuthorizer::class)->enforcing()],
    'events' => ['options.events.enabled', fn () => Options::set(ThemeOption::class, 'dark')],
]);
