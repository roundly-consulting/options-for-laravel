<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Options\OptionsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * One key per process, not per test: the key is config, and config is part of the kept
     * schema's signature — a new key each test would migrate each test.
     */
    private static ?string $appKey = null;

    /**
     * Every provider options needs, in registration order. A host auto-discovers these;
     * the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [OptionsServiceProvider::class];
    }

    /**
     * The options migration, named by **provider class** rather than by the directory it
     * happens to sit in: the base case reflects on the provider to find its
     * `database/migrations`, so a relocated directory can never silently stop being loaded.
     * The fixtures add the host-owned `users` table options hang off.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [OptionsServiceProvider::class, __DIR__.'/Fixtures/migrations'];
    }

    /**
     * Migrate once per worker and reset only the data between tests. The migration reads
     * config alone (the key type), which the schema signature already covers.
     */
    protected function keepsMigratedSchema(): bool
    {
        return true;
    }

    /**
     * Applied BEFORE the providers boot — which is where `app.key` has to be set, not in a
     * `defineEnvironment()` override. The base case does its whole job in
     * `defineEnvironment()` (DriverMatrix::configure + configBeforeBoot + model swaps), so
     * an override that forgot `parent::` would silently decapitate it: no error, no red,
     * DriverMatrix simply never configured and the pgsql leg quietly running sqlite.
     *
     * The key is load-bearing rather than boilerplate here — options ships encrypted casts,
     * and every one of them needs a real APP_KEY to round-trip.
     *
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'app.key' => self::$appKey ??= 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }
}
