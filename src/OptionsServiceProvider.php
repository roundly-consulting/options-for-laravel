<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Options\Commands\ClearOptionsCacheCommand;
use RoundlyConsulting\Options\Commands\ExportOptionsCommand;
use RoundlyConsulting\Options\Commands\GetOptionCommand;
use RoundlyConsulting\Options\Commands\ImportOptionsCommand;
use RoundlyConsulting\Options\Commands\ListOptionsCommand;
use RoundlyConsulting\Options\Commands\MakeOptionCommand;
use RoundlyConsulting\Options\Commands\SetOptionCommand;
use RoundlyConsulting\Options\Support\OptionStore;

final class OptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/options.php', 'options');

        $this->app->singleton(OptionsManager::class, fn (): OptionsManager => new OptionsManager);
        $this->app->singleton(OptionStore::class, fn (): OptionStore => new OptionStore);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Blade::directive('option', fn (string $expression): string => "<?php echo e(options({$expression})); ?>");

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeOptionCommand::class,
                ListOptionsCommand::class,
                GetOptionCommand::class,
                SetOptionCommand::class,
                ClearOptionsCacheCommand::class,
                ExportOptionsCommand::class,
                ImportOptionsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/options.php' => config_path('options.php'),
            ], 'options-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'options-migrations');
        }
    }
}
