<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Testing\FakeOptionsManager;

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
     * Swap the bound manager for an in-memory fake and return it.
     */
    public static function fake(): FakeOptionsManager
    {
        $fake = new FakeOptionsManager;

        app()->instance(OptionsManager::class, $fake);

        return $fake;
    }

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
        return $this->resolve($option, $owner)->value();
    }

    /**
     * Persist a new value for an option.
     */
    public function set(string $option, mixed $value, ?Model $owner = null): void
    {
        $this->resolve($option, $owner)->set($value);
    }

    /**
     * Whether a value is stored for the option in the given scope.
     */
    public function has(string $option, ?Model $owner = null): bool
    {
        return $this->resolveOption($option, $owner)->has();
    }

    /**
     * Delete the stored value, reverting to the declared default.
     */
    public function forget(string $option, ?Model $owner = null): void
    {
        $this->resolveOption($option, $owner)->forget();
    }

    /**
     * Alias of forget().
     */
    public function reset(string $option, ?Model $owner = null): void
    {
        $this->resolveOption($option, $owner)->reset();
    }

    /**
     * Get the stored value, or set and return the closure result if unset.
     */
    public function remember(string $option, Closure $callback, ?Model $owner = null): mixed
    {
        return $this->resolveOption($option, $owner)->remember($callback);
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
     * Write several options at once.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, ?Model $owner = null): void
    {
        foreach ($values as $option => $value) {
            $this->set($option, $value, $owner);
        }
    }

    /**
     * Eager-load every stored option for a scope as raw key => value pairs.
     *
     * @return Collection<string, mixed>
     */
    public function all(?Model $owner = null): Collection
    {
        /** @var class-string<Option> $model */
        $model = config('options.model', Option::class);

        return $model::query()
            ->forOwner($owner)
            ->get()
            ->mapWithKeys(fn (Option $option): array => [$option->key => $option->value]);
    }

    /**
     * Flush the in-request and persistent option value caches.
     */
    public function flushCache(): void
    {
        Cache::getInstance()->flush();
        app(OptionStore::class)->flush();
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
