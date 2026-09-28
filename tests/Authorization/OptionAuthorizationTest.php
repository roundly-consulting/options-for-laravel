<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Options\Exceptions\UnauthorizedOption;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\AdminOnlyOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(function (): void {
    Options::flushCache();
    AdminOnlyOption::$lastArgs = null;
});

it('does not enforce gated options when authorization is disabled', function (): void {
    config()->set('options.authorization.enabled', false);

    Options::set(AdminOnlyOption::class, 'value');

    expect(Options::get(AdminOnlyOption::class))->toBe('value');
});

it('blocks reads when authorizeRead denies', function (): void {
    config()->set('options.authorization.enabled', true);

    Options::get(AdminOnlyOption::class);
})->throws(UnauthorizedOption::class, 'Not authorized to read option [admin-only].');

it('blocks reads through the fluent api, helper and directive', function (): void {
    config()->set('options.authorization.enabled', true);

    expect(fn () => Options::option(AdminOnlyOption::class)->get())->toThrow(UnauthorizedOption::class)
        ->and(fn () => options(AdminOnlyOption::class))->toThrow(UnauthorizedOption::class);
});

it('blocks writes when authorizeWrite denies', function (): void {
    config()->set('options.authorization.enabled', true);

    Options::set(AdminOnlyOption::class, 'value');
})->throws(UnauthorizedOption::class, 'Not authorized to write option [admin-only].');

it('blocks forget when authorizeWrite denies', function (): void {
    config()->set('options.authorization.enabled', true);

    Options::forget(AdminOnlyOption::class);
})->throws(UnauthorizedOption::class);

it('passes the current user and owner to the hook', function (): void {
    config()->set('options.authorization.enabled', true);
    $user = User::query()->create();
    $owner = User::query()->create();

    Options::actingAs($user, fn () => Options::get(AdminOnlyOption::class, $owner));

    expect(AdminOnlyOption::$lastArgs['user'])->not->toBeNull()
        ->and(AdminOnlyOption::$lastArgs['user']->getAuthIdentifier())->toBe($user->getKey())
        ->and(AdminOnlyOption::$lastArgs['owner']->getKey())->toBe($owner->getKey());
});

it('authorizes as a given user and restores after', function (): void {
    config()->set('options.authorization.enabled', true);
    $user = User::query()->create();

    Options::actingAs($user, fn () => Options::set(AdminOnlyOption::class, 'ok'));

    expect(Options::actingAs($user, fn () => Options::get(AdminOnlyOption::class)))->toBe('ok');

    expect(fn () => Options::get(AdminOnlyOption::class))->toThrow(UnauthorizedOption::class);
});

it('restores the acting user even when the callback throws', function (): void {
    config()->set('options.authorization.enabled', true);
    $user = User::query()->create();

    try {
        Options::actingAs($user, function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // ignore
    }

    expect(fn () => Options::get(AdminOnlyOption::class))->toThrow(UnauthorizedOption::class);
});

it('bypasses enforcement within withoutAuthorization and restores after', function (): void {
    config()->set('options.authorization.enabled', true);

    Options::withoutAuthorization(fn () => Options::set(AdminOnlyOption::class, 'sys'));

    expect(Options::withoutAuthorization(fn () => Options::get(AdminOnlyOption::class)))->toBe('sys')
        ->and(fn () => Options::get(AdminOnlyOption::class))->toThrow(UnauthorizedOption::class);
});

it('restores the bypass flag even when the callback throws', function (): void {
    config()->set('options.authorization.enabled', true);

    try {
        Options::withoutAuthorization(function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // ignore
    }

    expect(fn () => Options::get(AdminOnlyOption::class))->toThrow(UnauthorizedOption::class);
});

it('consults the gate when use_gate is enabled and denies', function (): void {
    config()->set('options.authorization.enabled', true);
    config()->set('options.authorization.use_gate', true);
    $user = User::query()->create();

    Gate::define('option.read', fn (): bool => false);

    Options::actingAs($user, fn () => Options::get(ThemeOption::class));
})->throws(UnauthorizedOption::class);

it('allows when the gate ability is undefined', function (): void {
    config()->set('options.authorization.enabled', true);
    config()->set('options.authorization.use_gate', true);
    $user = User::query()->create();

    expect(Options::actingAs($user, fn () => Options::get(ThemeOption::class)))->toBe('light');
});

it('denies when the option hook allows but the gate denies', function (): void {
    config()->set('options.authorization.enabled', true);
    config()->set('options.authorization.use_gate', true);
    $user = User::query()->create();

    Gate::define('option.write', fn (): bool => false);

    Options::actingAs($user, fn () => Options::set(ThemeOption::class, 'dark'));
})->throws(UnauthorizedOption::class);

it('enforces per option in many()', function (): void {
    config()->set('options.authorization.enabled', true);

    Options::many([ThemeOption::class, AdminOnlyOption::class]);
})->throws(UnauthorizedOption::class);

it('builds unauthorized exception messages', function (): void {
    expect(UnauthorizedOption::read('a')->getMessage())->toBe('Not authorized to read option [a].')
        ->and(UnauthorizedOption::write('b')->getMessage())->toBe('Not authorized to write option [b].');
});

it('respects enforcement through the fake when enabled', function (): void {
    config()->set('options.authorization.enabled', true);
    $fake = Options::fake();

    expect(fn () => $fake->get(AdminOnlyOption::class))->toThrow(UnauthorizedOption::class);
});

it('leaves out of all() what the current user may not read', function (): void {
    Options::withoutAuthorization(function (): void {
        Options::set(AdminOnlyOption::class, 'secret-admin-value');
        Options::set(ThemeOption::class, 'dark');
    });
    // An option stored under a key no registered class answers for.
    Option::query()->create(['key' => 'unknown-key', 'value' => 'orphan']);
    Options::register(['admin' => AdminOnlyOption::class, 'theme' => ThemeOption::class]);

    config()->set('options.authorization.enabled', true);

    expect(Options::all()->all())->toBe(['theme' => 'dark'])
        ->and(Options::for(null)->all()->all())->toBe(['theme' => 'dark'])
        ->and(Options::actingAs(User::query()->create(), fn () => Options::all()->keys()->sort()->values()->all()))
        ->toBe(['admin-only', 'theme'])
        ->and(Options::withoutAuthorization(fn () => Options::all()->count()))->toBe(3);
});

it('returns everything stored from all() when authorization is off', function (): void {
    Options::set(AdminOnlyOption::class, 'secret-admin-value');
    Option::query()->create(['key' => 'unknown-key', 'value' => 'orphan']);

    expect(Options::all()->all())->toBe(['admin-only' => 'secret-admin-value', 'unknown-key' => 'orphan']);
});

it('leaves out of the fake all() what the current user may not read', function (): void {
    $fake = Options::fake();
    Options::register(['admin' => AdminOnlyOption::class, 'theme' => ThemeOption::class]);
    Options::set(AdminOnlyOption::class, 'secret-admin-value');
    Options::set(ThemeOption::class, 'dark');

    config()->set('options.authorization.enabled', true);

    expect($fake->all()->all())->toBe(['theme' => 'dark']);
});
