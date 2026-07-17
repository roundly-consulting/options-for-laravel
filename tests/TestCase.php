<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Options\OptionsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
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
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [OptionsServiceProvider::class];
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
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    /**
     * The host-owned entity options hang off. It is an ad-hoc `Schema::create()` rather
     * than a migration on purpose: `owner` is a polymorphic, deliberately unconstrained
     * `nullableMorphs`, so the owner's table is the host's business and nothing here is
     * shipped schema with an order to pin.
     */
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('users', fn (Blueprint $table) => $table->id());
    }
}
