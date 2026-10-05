<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Actions\ExportOptionsAction;
use RoundlyConsulting\Options\Actions\ImportOptionsAction;
use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;
use RoundlyConsulting\Options\Exceptions\InvalidOptionGroup;
use RoundlyConsulting\Options\Exceptions\InvalidOptionPayload;
use RoundlyConsulting\Options\Groups\OptionGroup;
use RoundlyConsulting\Options\Groups\PendingGroup;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\ConfigBridge;
use RoundlyConsulting\Options\Support\OptionAuthorizer;
use RoundlyConsulting\Options\Support\OptionModel;
use RoundlyConsulting\Options\Support\OptionObservers;
use RoundlyConsulting\Options\Support\OptionStore;
use Throwable;

/**
 * The facade root (`Options`), bound as a singleton. Every value operation —
 * including those called on an option instance, a `for()` / `option()` /
 * `group()` handle or the `HasOptions` trait — funnels through `get`, `set`,
 * `has`, `forget` and `import` here, which is what `OptionsFake` overrides.
 * Not final: `OptionsFake` extends it.
 */
class OptionsManager
{
    /**
     * Map of registered string keys to option class-strings.
     *
     * @var array<string, class-string<OptionInterface>>
     */
    protected array $registry = [];

    private bool $registrySeeded = false;

    /**
     * Register string keys for class-string options.
     *
     * @param  array<string, class-string<OptionInterface>>  $options
     */
    public function register(array $options): void
    {
        $this->seedRegistry();

        $this->registry = array_merge($this->registry, $options);
    }

    /**
     * @return array<string, class-string<OptionInterface>>
     */
    public function registered(): array
    {
        $this->seedRegistry();

        return $this->registry;
    }

    /**
     * Resolve a registered key or a raw class-string to an option class-string.
     *
     * @return class-string<OptionInterface>
     */
    public function resolveClass(string $option): string
    {
        $this->seedRegistry();

        if (isset($this->registry[$option])) {
            $option = $this->registry[$option];
        }

        if (! class_exists($option) || ! is_subclass_of($option, OptionInterface::class)) {
            throw InvalidOptionClassName::for($option);
        }

        return $option;
    }

    /**
     * Resolve an option instance, optionally bound to an owner model.
     */
    public function resolve(string $option, ?Model $owner = null): OptionInterface
    {
        return $this->resolveClass($option)::for($owner);
    }

    /**
     * Start a fluent, owner-scoped builder.
     */
    public function for(?Model $owner): PendingOptions
    {
        return new PendingOptions($this, $owner);
    }

    /**
     * Start a fluent builder for a single global option.
     */
    public function option(string $option): OptionContext
    {
        return $this->for(null)->option($option);
    }

    /**
     * Start a fluent builder for a single global option by registered key.
     */
    public function key(string $key): OptionContext
    {
        return $this->for(null)->key($key);
    }

    /**
     * Read the current value of an option.
     */
    public function get(string $option, ?Model $owner = null): mixed
    {
        $instance = $this->resolve($option, $owner);

        return $instance instanceof BaseOption ? $instance->loadValue() : $instance->value();
    }

    /**
     * Persist a new value for an option.
     */
    public function set(string $option, mixed $value, ?Model $owner = null): void
    {
        $instance = $this->resolve($option, $owner);

        if ($instance instanceof BaseOption) {
            $instance->storeValue($value);

            return;
        }

        $instance->set($value);
    }

    /**
     * Whether a value is stored for the option in the given scope.
     */
    public function has(string $option, ?Model $owner = null): bool
    {
        return $this->resolveOption($option, $owner)->isStored();
    }

    /**
     * Delete the stored value, reverting to the declared default.
     */
    public function forget(string $option, ?Model $owner = null): void
    {
        $this->resolveOption($option, $owner)->deleteValue();
    }

    /**
     * Alias of forget().
     */
    public function reset(string $option, ?Model $owner = null): void
    {
        $this->forget($option, $owner);
    }

    /**
     * Get the stored value, or set and return the closure result if unset.
     */
    public function remember(string $option, Closure $callback, ?Model $owner = null): mixed
    {
        if ($this->has($option, $owner)) {
            return $this->get($option, $owner);
        }

        // Another first caller may store between has() and here: write only if
        // nothing is stored yet, so every caller returns the one stored value.
        $this->setIfAbsent($option, $callback(), $owner);

        // What was stored, cast — the same type every later call returns.
        return $this->get($option, $owner);
    }

    /**
     * Read several options at once, keyed by the input identifier.
     *
     * @param  array<int, string>  $options
     * @return array<string, mixed>
     */
    public function many(array $options, ?Model $owner = null): array
    {
        $values = [];

        foreach ($options as $option) {
            $values[$option] = $this->get($option, $owner);
        }

        return $values;
    }

    /**
     * Write several options at once, all or nothing: every value is authorized
     * and validated before the first write, and the writes share a transaction.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, ?Model $owner = null): void
    {
        $options = [];

        foreach ($values as $option => $value) {
            $instance = $this->resolve($option, $owner);

            if ($instance instanceof BaseOption) {
                $instance->assertWritable($value);
            }

            $options[] = $instance;
        }

        $this->atomically($options, function () use ($values, $owner): void {
            foreach ($values as $option => $value) {
                $this->set($option, $value, $owner);
            }
        });
    }

    /**
     * Eager-load every stored option for a scope as raw key => value pairs.
     * With authorization enforced, only registered options the current user
     * may read are included: a stored key no registered class answers for
     * cannot be checked, so it is left out.
     *
     * @return Collection<string, mixed>
     */
    public function all(?Model $owner = null): Collection
    {
        $model = OptionModel::class();

        return $this->onlyReadable($model::query()
            ->forOwner($owner)
            ->get()
            ->mapWithKeys(fn (Option $option): array => [$option->key => $option->value]), $owner);
    }

    /**
     * Stored options as raw payloads (encrypted values stay encrypted). With an
     * owner: that owner's options; without: everything, or only the global
     * ones with `$globalOnly`.
     *
     * @return list<OptionPayload>
     */
    public function export(?Model $owner = null, bool $globalOnly = false): array
    {
        return app(ExportOptionsAction::class)->execute($owner, $globalOnly);
    }

    /**
     * `export()` as a JSON array of `{key, value, owner_type, owner_id}` rows.
     */
    public function exportJson(?Model $owner = null, bool $globalOnly = false): string
    {
        return json_encode(
            array_map(static fn (OptionPayload $payload): array => $payload->toArray(), $this->export($owner, $globalOnly)),
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Upsert exported options by scope + key: a JSON export, decoded rows or
     * `OptionPayload`s. Returns the number imported.
     *
     * @param  array<mixed>|string  $payload
     *
     * @throws InvalidOptionPayload
     */
    public function import(array|string $payload): int
    {
        return app(ImportOptionsAction::class)->execute($this->payloads($payload));
    }

    /**
     * Register a callback fired when the given option changes (set or forget).
     *
     * The callback receives (mixed $value, ?Model $owner, OptionChange $change)
     * and may be a Closure or an invokable class-string.
     *
     * @param  class-string<OptionInterface>|string  $option
     * @param  Closure|class-string  $callback
     */
    public function observe(string $option, Closure|string $callback): void
    {
        app(OptionObservers::class)->observe($option, $callback);
    }

    /**
     * Remove all observers registered for one option (class-string or key).
     *
     * @param  class-string<OptionInterface>|string  $option
     */
    public function forgetObservers(string $option): void
    {
        app(OptionObservers::class)->forget($option);
    }

    /**
     * Remove every registered observer.
     */
    public function flushObservers(): void
    {
        app(OptionObservers::class)->flush();
    }

    /**
     * Start a fluent builder for a setting group.
     *
     * @param  class-string<OptionGroup>|OptionGroup  $group
     */
    public function group(string|OptionGroup $group, ?Model $owner = null): PendingGroup
    {
        $instance = $group instanceof OptionGroup
            ? $group
            : $this->resolveGroup($this->resolveGroupClass($group));

        return new PendingGroup($this, $instance, $owner);
    }

    /**
     * Run the callback authorizing option access as the given user.
     */
    public function actingAs(?Authenticatable $user, Closure $callback): mixed
    {
        return app(OptionAuthorizer::class)->actingAs($user, $callback);
    }

    /**
     * Disable enforcement for the duration of the callback.
     */
    public function withoutAuthorization(Closure $callback): mixed
    {
        return app(OptionAuthorizer::class)->withoutAuthorization($callback);
    }

    /**
     * Register (or override) a config-key → option mapping at runtime.
     *
     * @param  class-string<OptionInterface>|string  $option
     */
    public function overrides(string $configKey, string $option): void
    {
        app(ConfigBridge::class)->add($configKey, $this->resolveClass($option));
    }

    /**
     * The active config override map.
     *
     * @return array<string, class-string<OptionInterface>>
     */
    public function configOverrides(): array
    {
        return app(ConfigBridge::class)->mappings();
    }

    /**
     * Re-read every mapped option and push values into config() now.
     */
    public function applyConfigOverrides(): void
    {
        app(ConfigBridge::class)->apply();
    }

    /**
     * Flush the in-request and persistent option value caches.
     */
    public function flushCache(): void
    {
        app(OptionStore::class)->flush();
        app(Cache::class)->flush();
    }

    /**
     * @internal resolves a BaseOption for the config bridge; hosts use `resolve()`
     */
    public function resolveOptionInstance(string $option, ?Model $owner = null): BaseOption
    {
        return $this->resolveOption($option, $owner);
    }

    /**
     * Resolve to a BaseOption so the extended state operations are available.
     */
    protected function resolveOption(string $option, ?Model $owner = null): BaseOption
    {
        $instance = $this->resolve($option, $owner);

        if (! $instance instanceof BaseOption) {
            throw InvalidOptionClassName::for($option);
        }

        return $instance;
    }

    /**
     * The write behind `remember()`: store the value unless one is stored by now.
     * `OptionsFake` overrides it.
     */
    protected function setIfAbsent(string $option, mixed $value, ?Model $owner = null): void
    {
        $this->resolveOption($option, $owner)->storeValueIfAbsent($value);
    }

    /**
     * Run a batch of writes in one transaction on the option model's connection.
     *
     * @param  list<OptionInterface>  $options
     * @param  Closure(): void  $writes
     */
    protected function atomically(array $options, Closure $writes): void
    {
        $model = OptionModel::class();

        try {
            (new $model)->getConnection()->transaction($writes);
        } catch (Throwable $exception) {
            // The rows rolled back, but the caches may already hold new values.
            foreach ($options as $option) {
                if ($option instanceof BaseOption) {
                    $option->forgetCachedValue();
                }
            }

            throw $exception;
        }
    }

    /**
     * Drop the values the current user may not read, when authorization is enforced.
     *
     * @param  Collection<string, mixed>  $values
     * @return Collection<string, mixed>
     */
    protected function onlyReadable(Collection $values, ?Model $owner): Collection
    {
        $authorizer = app(OptionAuthorizer::class);

        if (! $authorizer->enforcing()) {
            return $values;
        }

        $classes = [];

        foreach ($this->registered() as $class) {
            $classes[$class::for(null)->key()] = $class;
        }

        return $values->filter(function (mixed $value, string $key) use ($classes, $owner, $authorizer): bool {
            if (! isset($classes[$key])) {
                return false;
            }

            $option = $classes[$key]::for($owner);

            return ! $option instanceof BaseOption || $authorizer->allowsRead($option, $owner);
        });
    }

    /**
     * @param  array<mixed>|string  $payload
     * @return list<OptionPayload>
     *
     * @throws InvalidOptionPayload
     */
    protected function payloads(array|string $payload): array
    {
        return is_string($payload) ? OptionPayload::listFromJson($payload) : OptionPayload::list($payload);
    }

    /**
     * Resolve a registered group key or a raw class-string to a group class-string.
     *
     * @return class-string<OptionGroup>
     */
    private function resolveGroupClass(string $group): string
    {
        /** @var array<string, class-string<OptionGroup>> $groups */
        $groups = config('options.groups', []);

        if (isset($groups[$group])) {
            $group = $groups[$group];
        }

        if (! class_exists($group) || ! is_subclass_of($group, OptionGroup::class)) {
            throw InvalidOptionGroup::for($group);
        }

        return $group;
    }

    /**
     * @param  class-string<OptionGroup>  $group
     */
    private function resolveGroup(string $group): OptionGroup
    {
        $instance = app()->make($group);

        if (! $instance instanceof OptionGroup) {
            throw InvalidOptionGroup::for($group);
        }

        return $instance;
    }

    private function seedRegistry(): void
    {
        if ($this->registrySeeded) {
            return;
        }

        $this->registrySeeded = true;

        /** @var array<string, class-string<OptionInterface>> $configured */
        $configured = config('options.registry', []);

        $this->registry = array_merge($configured, $this->registry);
    }
}
