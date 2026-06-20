<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Options\Events\OptionForgotten;
use RoundlyConsulting\Options\Events\OptionResolved;
use RoundlyConsulting\Options\Events\OptionSet;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => Cache::getInstance()->flush());

it('dispatches OptionSet on write', function (): void {
    Event::fake([OptionSet::class]);

    Options::set(ThemeOption::class, 'dark');

    Event::assertDispatched(
        OptionSet::class,
        fn (OptionSet $event): bool => $event->key === 'theme'
            && $event->value === 'dark'
            && $event->owner === null,
    );
});

it('dispatches OptionForgotten on forget', function (): void {
    Options::set(ThemeOption::class, 'dark');

    Event::fake([OptionForgotten::class]);

    Options::forget(ThemeOption::class);

    Event::assertDispatched(
        OptionForgotten::class,
        fn (OptionForgotten $event): bool => $event->key === 'theme',
    );
});

it('carries owner details on the event payload', function (): void {
    $user = User::create();

    Event::fake([OptionSet::class]);

    Options::for($user)->set(ThemeOption::class, 'dark');

    Event::assertDispatched(
        OptionSet::class,
        fn (OptionSet $event): bool => $event->owner?->is($user) === true,
    );
});

it('does not dispatch OptionResolved by default', function (): void {
    Options::set(ThemeOption::class, 'dark');

    Event::fake([OptionResolved::class]);

    Options::get(ThemeOption::class);

    Event::assertNotDispatched(OptionResolved::class);
});

it('dispatches OptionResolved when enabled', function (): void {
    config()->set('options.events.resolved', true);

    Options::set(ThemeOption::class, 'dark');

    Event::fake([OptionResolved::class]);

    Options::get(ThemeOption::class);

    Event::assertDispatched(
        OptionResolved::class,
        fn (OptionResolved $event): bool => $event->key === 'theme' && $event->value === 'dark',
    );
});

it('does not dispatch OptionResolved when events are globally disabled', function (): void {
    config()->set('options.events.enabled', false);
    config()->set('options.events.resolved', true);

    Options::set(ThemeOption::class, 'dark');

    Event::fake([OptionResolved::class]);

    Options::get(ThemeOption::class);

    Event::assertNotDispatched(OptionResolved::class);
});

it('suppresses all events when disabled', function (): void {
    config()->set('options.events.enabled', false);

    Event::fake([OptionSet::class, OptionForgotten::class]);

    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);

    Event::assertNotDispatched(OptionSet::class);
    Event::assertNotDispatched(OptionForgotten::class);
});
