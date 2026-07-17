<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Tests\Models\CustomOption;
use RoundlyConsulting\Options\Tests\Models\SwappedOptionTestCase;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\SecretOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

/**
 * The model-swap proof (S) for the `options.model` seam, driven through the REAL flows.
 *
 * `tests/Support/OptionModelTest.php` already covers the *resolver* — that
 * `OptionModel::class()` returns what the config names. That is a real claim and it is kept.
 * What it cannot cover is the two things this file exists for:
 *
 *  - it sets the config at runtime, so every listener the provider hung at boot is still on
 *    the packaged Option (media #28);
 *  - it asserts `instanceof` / `->exists()`, which pass for a row created as the packaged
 *    class — a row that never fires the host's model events (permissions #31). Only the
 *    concrete class, plus a `created` event counted on the subclass itself, proves the row
 *    was really made as the host's model.
 *
 * The swap is applied before boot by {@see SwappedOptionTestCase}, which this directory is
 * bound to — Pest binds a test case per directory, not per file.
 */
it('honours a host option model through every write flow', function (): void {
    expect('options.model')->toHonourModelSwap(CustomOption::class, function (): array {
        $user = User::query()->create();

        // The facade, an owned option, and an encrypted one — the paths a host actually
        // reaches this seam through.
        Options::set(ThemeOption::class, 'dark');
        ThemeOption::for($user)->set('light');
        SecretOption::make()->set('sk_live_deadbeef');

        return [
            ...CustomOption::query()->get()->all(),
        ];
    });
});

/**
 * The cache is the path most likely to bypass a swap silently: a value read back from the
 * option store must still have been written by, and re-hydrate as, the host's model — not
 * a packaged row the cache happens to answer from.
 */
it('reads through the swapped model after a cache round-trip', function (): void {
    $user = User::query()->create();

    ThemeOption::for($user)->set('dark');

    expect(ThemeOption::for($user)->value())->toBe('dark')
        ->and(Option::query()->count())->toBe(1);

    $row = CustomOption::query()->first();

    // The concrete class, not just `instanceof`: a row hydrated as the packaged class would
    // satisfy an `instanceof Option` check and still be the wrong model.
    expect($row::class)->toBe(CustomOption::class)
        ->and(Option::class)->not->toBe(CustomOption::class);
});

// The structural half of the seam — Option is non-final, and `options.model` really
// defaults to the packaged model — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that
// preset asserts the config *default*, which this directory has swapped away.
