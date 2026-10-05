<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Options\DataTransferObjects\OptionChange;
use RoundlyConsulting\Options\Enums\OptionChangeType;
use RoundlyConsulting\Options\Events\OptionForgotten;
use RoundlyConsulting\Options\Events\OptionSet;
use RoundlyConsulting\Options\Exceptions\InvalidOptionObserver;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\LocaleOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\Options\Tests\Support\NotInvokable;
use RoundlyConsulting\Options\Tests\Support\RecordingObserver;

beforeEach(function (): void {
    RecordingObserver::$calls = [];
    Options::flushObservers();
});

it('fires an observer on set with the change payload', function (): void {
    $captured = [];

    Options::observe(ThemeOption::class, function (mixed $value, $owner, OptionChange $change) use (&$captured): void {
        $captured = [$value, $owner, $change];
    });

    Options::set(ThemeOption::class, 'dark');

    expect($captured[0])->toBe('dark')
        ->and($captured[1])->toBeNull()
        ->and($captured[2])->toBeInstanceOf(OptionChange::class)
        ->and($captured[2]->type)->toBe(OptionChangeType::Set)
        ->and($captured[2]->key)->toBe('theme')
        ->and($captured[2]->optionClass)->toBe(ThemeOption::class)
        ->and($captured[2]->value)->toBe('dark');
});

it('fires an observer on forget with a forgotten change', function (): void {
    $captured = null;

    Options::observe(ThemeOption::class, function (mixed $value, $owner, OptionChange $change) use (&$captured): void {
        $captured = $change;
    });

    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);

    expect($captured->type)->toBe(OptionChangeType::Forgotten)
        ->and($captured->value)->toBeNull();
});

it('fires observers in registration order', function (): void {
    $order = [];

    Options::observe(ThemeOption::class, function () use (&$order): void {
        $order[] = 'first';
    });
    Options::observe(ThemeOption::class, function () use (&$order): void {
        $order[] = 'second';
    });

    Options::set(ThemeOption::class, 'dark');

    expect($order)->toBe(['first', 'second']);
});

it('resolves an invokable class-string observer from the container', function (): void {
    Options::observe(ThemeOption::class, RecordingObserver::class);

    Options::set(ThemeOption::class, 'dark');

    expect(RecordingObserver::$calls)->toHaveCount(1)
        ->and(RecordingObserver::$calls[0]['value'])->toBe('dark');
});

it('targets the same option whether registered by key or class', function (): void {
    Options::register(['theme' => ThemeOption::class]);

    $hits = 0;
    Options::observe('theme', function () use (&$hits): void {
        $hits++;
    });

    Options::set(ThemeOption::class, 'dark');

    expect($hits)->toBe(1);
});

it('passes the owner scope to observers', function (): void {
    $user = User::query()->create();

    $captured = null;
    Options::observe(ThemeOption::class, function (mixed $value, $owner) use (&$captured): void {
        $captured = $owner;
    });

    Options::set(ThemeOption::class, 'dark', $user);

    expect($captured)->not->toBeNull()
        ->and($captured->getKey())->toBe($user->getKey());
});

it('forgets observers for one option only', function (): void {
    Options::register(['locale' => LocaleOption::class]);

    $theme = 0;
    $locale = 0;

    Options::observe(ThemeOption::class, function () use (&$theme): void {
        $theme++;
    });
    Options::observe('locale', function () use (&$locale): void {
        $locale++;
    });

    Options::forgetObservers(ThemeOption::class);

    Options::set(ThemeOption::class, 'dark');
    Options::set('locale', 'sk');

    expect($theme)->toBe(0)
        ->and($locale)->toBe(1);
});

it('flushes every observer', function (): void {
    $hits = 0;
    Options::observe(ThemeOption::class, function () use (&$hits): void {
        $hits++;
    });

    Options::flushObservers();
    Options::set(ThemeOption::class, 'dark');

    expect($hits)->toBe(0);
});

it('does not fire observers on read', function (): void {
    $hits = 0;
    Options::observe(ThemeOption::class, function () use (&$hits): void {
        $hits++;
    });

    Options::get(ThemeOption::class);

    expect($hits)->toBe(0);
});

it('fires observers whether or not events run', function (Closure $silenceEvents): void {
    // Regression (2026-10-05 chat review, C-4): observers hung off the OptionSet /
    // OptionForgotten listeners, so `options.events.enabled=false` or a host test's
    // Event::fake() silently switched them off — while Options::fake() kept firing them.
    // This replaces a test that pinned that behaviour.
    $silenceEvents();

    $seen = [];
    Options::observe(ThemeOption::class, function (mixed $value, $owner, OptionChange $change) use (&$seen): void {
        $seen[] = $change->type->value.':'.var_export($value, true);
    });

    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);

    expect($seen)->toBe(["set:'dark'", 'forgotten:NULL']);
})->with([
    'events disabled' => [fn () => config()->set('options.events.enabled', false)],
    // A host test asserting the package's events fakes them; a blanket Event::fake()
    // would also fake the Option model's own saving hook.
    'Event::fake() of the option events' => [fn () => Event::fake([OptionSet::class, OptionForgotten::class])],
]);

it('still fires observers through the fake when events are disabled', function (): void {
    config()->set('options.events.enabled', false);
    $fake = Options::fake();

    $hits = 0;
    Options::observe(ThemeOption::class, function () use (&$hits): void {
        $hits++;
    });

    $fake->set(ThemeOption::class, 'dark');

    expect($hits)->toBe(1);
});

it('rejects a non-invokable class-string observer', function (): void {
    Options::observe(ThemeOption::class, NotInvokable::class);
})->throws(InvalidOptionObserver::class);

it('constructs the OptionChange DTO and enum', function (): void {
    $change = new OptionChange('theme', ThemeOption::class, OptionChangeType::Set, 'dark', null);

    expect($change->key)->toBe('theme')
        ->and($change->type->value)->toBe('set')
        ->and(OptionChangeType::Forgotten->value)->toBe('forgotten');
});
