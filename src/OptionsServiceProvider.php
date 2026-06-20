<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Options\Commands\ClearOptionsCacheCommand;
use RoundlyConsulting\Options\Commands\ExportOptionsCommand;
use RoundlyConsulting\Options\Commands\GetOptionCommand;
use RoundlyConsulting\Options\Commands\ImportOptionsCommand;
use RoundlyConsulting\Options\Commands\ListOptionsCommand;
use RoundlyConsulting\Options\Commands\MakeOptionCommand;
use RoundlyConsulting\Options\Commands\MakeOptionGroupCommand;
use RoundlyConsulting\Options\Commands\SetOptionCommand;
use RoundlyConsulting\Options\Enums\OptionChangeType;
use RoundlyConsulting\Options\Events\OptionForgotten;
use RoundlyConsulting\Options\Events\OptionSet;
use RoundlyConsulting\Options\Support\ConfigBridge;
use RoundlyConsulting\Options\Support\OptionAuthorizer;
use RoundlyConsulting\Options\Support\OptionObservers;
use RoundlyConsulting\Options\Support\OptionStore;

final class OptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/options.php', 'options');

        $this->app->singleton(OptionsManager::class, fn (): OptionsManager => new OptionsManager);
        $this->app->singleton(OptionStore::class, fn (): OptionStore => new OptionStore);
        $this->app->singleton(OptionObservers::class, fn ($app): OptionObservers => new OptionObservers($app->make(OptionsManager::class)));
        $this->app->singleton(OptionAuthorizer::class, fn (): OptionAuthorizer => new OptionAuthorizer);
        $this->app->singleton(ConfigBridge::class, fn ($app): ConfigBridge => new ConfigBridge($app->make(OptionsManager::class)));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Blade::directive('option', fn (string $expression): string => "<?php echo e(options({$expression})); ?>");

        $this->registerObserverListeners();
        $this->bootConfigBridge();

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeOptionCommand::class,
                MakeOptionGroupCommand::class,
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

    private function registerObserverListeners(): void
    {
        $observers = $this->app->make(OptionObservers::class);

        Event::listen(OptionSet::class, function (OptionSet $event) use ($observers): void {
            $observers->dispatch($event->key, OptionChangeType::Set, $event->value, $event->owner);
        });

        Event::listen(OptionForgotten::class, function (OptionForgotten $event) use ($observers): void {
            $observers->dispatch($event->key, OptionChangeType::Forgotten, null, $event->owner);
        });
    }

    private function bootConfigBridge(): void
    {
        $bridge = $this->app->make(ConfigBridge::class);

        /** @var array<string, class-string<OptionInterface>> $map */
        $map = config('options.config_overrides', []);

        $bridge->seedFromConfig($map);

        if ($bridge->hasMappings()) {
            rescue(fn () => $bridge->apply(), report: false);
        }

        Event::listen(OptionSet::class, function (OptionSet $event) use ($bridge): void {
            rescue(fn () => $bridge->syncOptionKey($event->key), report: false);
        });

        Event::listen(OptionForgotten::class, function (OptionForgotten $event) use ($bridge): void {
            rescue(fn () => $bridge->syncOptionKey($event->key), report: false);
        });
    }
}
