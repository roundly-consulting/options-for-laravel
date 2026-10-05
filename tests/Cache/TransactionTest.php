<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use RoundlyConsulting\Options\Events\OptionForgotten;
use RoundlyConsulting\Options\Events\OptionSet;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\ConfigBridge;
use RoundlyConsulting\Options\Tests\Options\LocaleOption;
use RoundlyConsulting\Options\Tests\Options\MailFromOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

/*
 * Regression (2026-10-05 chat review, C-1): a write cached its value persistently and told
 * observers, listeners and the live config bridge before the enclosing transaction
 * committed. A rollback left every process serving a value the database never held, for
 * the whole cache TTL, and everyone had been told about a change that never happened.
 */

beforeEach(function (): void {
    $this->cacheDirectory = useFileOptionCache();
    Options::flushCache();
    Options::flushObservers();
    app(ConfigBridge::class)->seedFromConfig([]);

    $this->seen = [];
    Options::observe(ThemeOption::class, function (mixed $value): void {
        $this->seen[] = $value;
    });
});

afterEach(fn () => File::deleteDirectory($this->cacheDirectory));

/**
 * A fresh request: its own in-request memo, the same persistent cache and database.
 */
function freshRequest(): void
{
    app()->forgetInstance(Cache::class);
}

it('caches and announces nothing from a set that rolls back', function (): void {
    Event::fake([OptionSet::class]);
    config()->set('options.config_overrides_live', true);
    config()->set('mail.from.address', 'original@test');
    Options::overrides('mail.from.address', MailFromOption::class);

    expect(fn () => DB::transaction(function (): void {
        Options::set(ThemeOption::class, 'dark');
        Options::set(MailFromOption::class, 'rolled-back@test');

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    freshRequest();

    expect(Option::query()->count())->toBe(0)
        ->and(Options::get(ThemeOption::class))->toBe('light')
        ->and($this->seen)->toBe([])
        ->and(config('mail.from.address'))->toBe('original@test');

    Event::assertNotDispatched(OptionSet::class);
});

it('announces nothing from a forget that rolls back', function (): void {
    Options::set(ThemeOption::class, 'dark');
    $this->seen = [];
    Event::fake([OptionForgotten::class]);

    expect(fn () => DB::transaction(function (): void {
        Options::forget(ThemeOption::class);

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    freshRequest();

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and($this->seen)->toBe([]);

    Event::assertNotDispatched(OptionForgotten::class);
});

it('tells no observer about a batch that rolls back', function (): void {
    Option::saving(function (Option $option): void {
        if ($option->key === 'locale') {
            throw new RuntimeException('disk full');
        }
    });

    expect(fn () => Options::setMany([ThemeOption::class => 'dark', LocaleOption::class => 'sk']))
        ->toThrow(RuntimeException::class, 'disk full');

    expect($this->seen)->toBe([]);
});

it('caches and announces a committed write once, after the commit', function (): void {
    Event::fake([OptionSet::class]);
    $seenInside = null;

    DB::transaction(function () use (&$seenInside): void {
        Options::set(ThemeOption::class, 'dark');

        $seenInside = $this->seen;
    });

    freshRequest();
    DB::enableQueryLog();

    expect($seenInside)->toBe([])
        ->and($this->seen)->toBe(['dark'])
        ->and(Options::get(ThemeOption::class))->toBe('dark')
        ->and(DB::getQueryLog())->toBe([]);

    Event::assertDispatchedTimes(OptionSet::class, 1);
});

it('reads its own write inside the transaction', function (): void {
    DB::transaction(function (): void {
        Options::set(ThemeOption::class, 'dark');

        expect(Options::get(ThemeOption::class))->toBe('dark');
    });
});
