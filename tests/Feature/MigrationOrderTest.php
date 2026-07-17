<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Options\OptionsServiceProvider;
use RoundlyConsulting\Options\Tests\Options\SecretOption;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Options ships exactly one CREATE and zero foreign keys — an option's owner is a
 * polymorphic `nullableMorphs('owner')`, deliberately unconstrained because a host's owner
 * can live in any table (and a global option has no owner at all).
 *
 * That shape decides what is worth pinning here, and it is worth being explicit about why:
 *
 *  - **M (`toHaveRunnableMigrationOrder`) is not adopted.** With one migration and no FK
 *    edges there is no order to get wrong. `foreignKeys: 0` would pin a number that cannot
 *    change without a schema change, over a directory holding a single file.
 *  - **The R negative control (`toRejectBrokenOrderOnConnection`) is not adoptable.** It
 *    asserts the engine *refuses* a reordered set — but reversing a one-file list is the
 *    same list, and with no foreign keys Postgres has nothing to refuse. It fails loudly by
 *    design: the assertion working correctly against a shape it does not fit, not a red to
 *    chase.
 */
$migrations = __DIR__.'/../../database/migrations';

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `count: 1` pins the file count so neither check can pass over an empty
 * or relocated directory.
 */
it('never auto-loads its migration — the host publishes it', function (): void {
    expect(OptionsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migration timestamp-injected into the host', function (): void {
    expect(OptionsServiceProvider::class)->toPublishMigrationsTimestamped('options-migrations', 1);
});

/**
 * R — the real-engine proof. Options' DDL had never met a real engine: the suite ran on
 * SQLite for the package's whole life. `migrations: 1` pins the count, and the expectation
 * additionally fails a set that "applies cleanly" while creating no tables — an empty
 * `up()` otherwise passes and proves nothing.
 */
it('applies its migration on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 1);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The `text` value column, the `json` meta column and the composite owner index are what
 * the drivers render differently, so a round-trip on whatever engine the leg configured is
 * what proves the columns are usable rather than merely creatable.
 *
 * An **encrypted** option is the payload worth round-tripping: a ciphertext is a long
 * opaque blob, and `value` is `text` rather than `string` precisely so it fits. That is a
 * column-type claim no SQLite run can really test — SQLite ignores declared lengths
 * entirely, so a `string` column would have passed there and truncated on a real engine.
 *
 * The last assertion is the driver-truth pin: it compares the **env-declared** driver
 * against what the **connection itself answers**, so a leg that quietly stayed on SQLite
 * fails here instead of passing as a "postgres" run. It fires automatically, rather than
 * needing someone to read a skip count.
 */
it('round-trips an encrypted option on the configured engine', function (): void {
    $secret = str_repeat('sk_live_deadbeef', 40); // 640 chars; longer once encrypted.

    SecretOption::make()->set($secret);

    expect(SecretOption::make()->value())->toBe($secret)
        // Stored encrypted: the plaintext must never be the column's contents.
        ->and(DB::table('options')->where('key', SecretOption::make()->key())->value('value'))
        ->not->toBe($secret)
        // The driver actually under test, so a leg that quietly stayed on sqlite is visible
        // in the failure rather than passing as a "postgres" run.
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});
