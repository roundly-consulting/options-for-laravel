<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Tests\Options\SecretOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\Options\Tests\Settings\AppearanceSettings;

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous — passing against empty output.
 *
 * Options' existing `doesntExpectOutputToContain` checks in ServiceProviderTest are real
 * captures rather than that vacuous shape, and they are kept. This adds the ordered pin:
 * output non-empty → every `mustRender` string present → only then no secret renders. The
 * ordering is the point, because a negative-only check passes against empty output.
 *
 * Options is the package where this matters most in the whole fleet: **an option key names
 * a host's setting and its value is arbitrary host data** — feature flags, credentials,
 * PII. `options.registry` and `options.config_overrides` are keyed by things like
 * `services.stripe.secret`, and `options.cache.store` names a host's store. All of them are
 * reported by count or as SET/DEFAULT, never by name, and a stored *value* must never
 * render at all.
 */
it('renders the options section without leaking the settings it stores', function (): void {
    // A real stored value, and a real *encrypted* stored value — neither may reach `about`.
    // Written before the config below is pointed at a store that does not exist: the writes
    // must go through the real cache, and it is only the *reporting* of the store name that
    // is under test here.
    Options::set(ThemeOption::class, 'midnight-tokyo');
    SecretOption::make()->set('sk_live_deadbeef');

    config()->set('options.registry', ['stripe.secret' => ThemeOption::class]);
    config()->set('options.groups', ['billing-secrets' => AppearanceSettings::class]);
    config()->set('options.config_overrides', ['services.stripe.secret' => ThemeOption::class]);
    config()->set('options.cache.store', 'redis-secrets');

    expect('options')->toLeakNoSecrets(
        secrets: [
            // Registered key names: a key names a host's setting, and this one is literally
            // a credential's path.
            'stripe.secret',
            'billing-secrets',
            'services.stripe.secret',
            // The host's cache store name.
            'redis-secrets',
            // Stored values — the payload itself, plaintext or not.
            'midnight-tokyo',
            'sk_live_deadbeef',
        ],
        mustRender: [
            'Model',
            'Registry',
            'Groups',
            'Cache',
            'Cache store',
            'Cache TTL',
            'Events',
            'Authorization',
            'Config overrides',
            // The counts themselves must render — the positive proof that these lines
            // report rather than sitting silently empty, which is what would make the
            // secret half pass for the wrong reason.
            '1 key(s)',
            '1 group(s)',
            // The store is reported as SET, never named.
            'SET',
        ],
    );
});
