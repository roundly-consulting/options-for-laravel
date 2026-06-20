<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Illuminate\Support\ServiceProvider;

final class OptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/options.php', 'options');

        $this->app->singleton(OptionsManager::class, fn (): OptionsManager => new OptionsManager);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/options.php' => config_path('options.php'),
            ], 'options-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'options-migrations');
        }
    }
}
