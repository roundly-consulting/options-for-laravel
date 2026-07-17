<?php

declare(strict_types=1);

/**
 * The config contract options never had, pinned in both directions:
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`; 330
 *    tests stayed green because the suite set the same wrong key.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead config
 *    that lies to the host: media #27's `max_file_size` cap that never applied, alerts
 *    #24's thrice-documented `escalation` key, and query-builder #32.
 *
 * Options is the package where the reverse direction earns its keep most: its config file
 * is the largest surface here (cache, events, authorization, config_overrides), it is
 * almost entirely `env()`-driven, and a host reading the file has no way to tell a live
 * knob from a dead one.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/options.php')->toSatisfyConfigContract([__DIR__.'/../../src', __DIR__.'/../../database'], [
        // `options.model` is read through the toolkit's `ModelResolver::for('options.model',
        // …)` seam rather than a `config()` call. It is a real read — it drives the whole
        // model swap — but it is not a `config(` token, so the prefix is what makes it
        // visible to the scraper.
        'extraReadPrefixes' => ['options.'],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but this provider's `contributesToAbout()` closure calls
        // `config('options.…')` for real, and `bootConfigBridge()` is the only reader of
        // `options.config_overrides`. Excluding it would discard the only reader of several
        // keys and weaken the reverse direction for nothing.
    ]);
});
