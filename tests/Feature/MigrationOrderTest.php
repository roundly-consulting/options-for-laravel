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

/*
 * R (`toApplyOnConnection('pgsql', migrations: 1)`) is DEFERRED, not rejected — it belongs
 * here and should land once the testing package is fixed.
 *
 * It cannot be adopted today because it is **not safe to run alongside its own suite**. On
 * the pgsql leg, `DriverMatrix::configure()` builds `connections.testing` and
 * `connections.pgsql` from the same `connectionConfig('pgsql')` — identical host, port and
 * database. They are one physical database reached through two PDO sessions.
 * `MigrationRunner::runFiles()` calls `dropAllTables()` on entry and again in its `finally`,
 * so the assertion drops the live suite's tables mid-run, and `executionOrder="random"`
 * decides whether anything was still using them.
 *
 * Measured on the sibling addresses row, on a database isolated from every other suite, with
 * R present: 6 runs gave 5 × 94 passed and 1 × 13 failed. It is ~1-in-6, seed-dependent, and
 * it fails *elsewhere* — innocent tests die, not this one. A green pgsql leg with R in it is
 * therefore evidence of a lucky seed and nothing else, which is the precise failure this
 * whole plan exists to kill: an assertion that cannot be trusted when it passes.
 *
 * Everything else on this row is unaffected and stays: P above is a structural check on the
 * provider and needs no engine at all, and the round-trip is an ordinary test on the default
 * connection.
 */

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
