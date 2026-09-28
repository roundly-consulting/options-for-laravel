<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
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
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\ConfigBridge;
use RoundlyConsulting\Options\Support\OptionAuthorizer;
use RoundlyConsulting\Options\Support\OptionModel;
use RoundlyConsulting\Options\Support\OptionObservers;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBladeDirectives;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class OptionsServiceProvider extends PackageServiceProvider
{
    use RegistersBladeDirectives;
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('options')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasCommands([
                MakeOptionCommand::class,
                MakeOptionGroupCommand::class,
                ListOptionsCommand::class,
                GetOptionCommand::class,
                SetOptionCommand::class,
                ClearOptionsCacheCommand::class,
                ExportOptionsCommand::class,
                ImportOptionsCommand::class,
            ])
            ->contributesToAbout(static fn (): array => [
                'Model' => class_basename(OptionModel::class()),
                // Option keys, group keys and bridged config paths are reported
                // by count only: a key names a host's setting (feature flags,
                // credentials, PII) and its value is arbitrary host data.
                'Registry' => self::countOf('options.registry').' key(s)',
                'Groups' => self::countOf('options.groups').' group(s)',
                'Cache' => self::switch('options.cache.enabled', true),
                'Cache store' => config('options.cache.store') === null ? 'DEFAULT' : 'SET',
                'Cache TTL' => self::cacheTtl(),
                'Events' => self::switch('options.events.enabled', true),
                'Resolved events' => self::switch('options.events.resolved', false),
                'Authorization' => self::switch('options.authorization.enabled', false),
                'Authorization gate' => self::switch('options.authorization.use_gate', false),
                'Config overrides' => self::countOf('options.config_overrides').' key(s)',
                'Config overrides live' => self::switch('options.config_overrides_live', false),
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(OptionsManager::class, fn (): OptionsManager => new OptionsManager);
        $this->app->singleton(OptionStore::class, fn (): OptionStore => new OptionStore);
        $this->app->scoped(Cache::class, fn (): Cache => new Cache);
        $this->app->singleton(OptionObservers::class, fn (): OptionObservers => new OptionObservers);
        $this->app->singleton(OptionAuthorizer::class, fn (): OptionAuthorizer => new OptionAuthorizer);
        $this->app->singleton(ConfigBridge::class, fn (): ConfigBridge => new ConfigBridge);
    }

    public function boot(): void
    {
        parent::boot();

        // The options migration keys its `owner` polymorphic column through the toolkit's
        // `morphKey` macro, so it must exist before the migration runs. Registration is
        // idempotent — the toolkit guards it with `hasMacro()`.
        $this->registerBlueprintMacros();

        $this->registerBladeDirective(
            'option',
            fn (string $expression): string => "<?php echo e(options({$expression})); ?>",
        );

        $this->registerObserverListeners();
        $this->resetMemoPerLifecycle();
        $this->bootConfigBridge();
    }

    /**
     * The memo is `scoped`, which Laravel already resets between queued jobs; these
     * listeners also cover workers and Octane setups that do not, so one job or
     * request never serves another's reads.
     */
    private function resetMemoPerLifecycle(): void
    {
        Event::listen(
            [JobProcessing::class, 'Laravel\Octane\Events\RequestReceived'],
            static function (): void {
                app()->forgetInstance(Cache::class);
            },
        );
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

    private static function switch(string $key, bool $default): string
    {
        return (bool) config($key, $default) ? 'ON' : 'OFF';
    }

    private static function countOf(string $key): string
    {
        $value = config($key, []);

        return (string) (is_array($value) ? count($value) : 0);
    }

    private static function cacheTtl(): string
    {
        $ttl = config('options.cache.ttl', 3600);

        return (is_numeric($ttl) ? (int) $ttl : 3600).'s';
    }
}
