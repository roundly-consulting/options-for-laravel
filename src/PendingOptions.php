<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;

/**
 * Owner-scoped fluent builder.
 */
final class PendingOptions
{
    public function __construct(
        private readonly OptionsManager $manager,
        private readonly ?Model $owner = null,
    ) {}

    public function option(string $option): OptionContext
    {
        return new OptionContext($this->resolveOption($option));
    }

    public function key(string $key): OptionContext
    {
        return $this->option($key);
    }

    public function get(string $option): mixed
    {
        return $this->manager->get($option, $this->owner);
    }

    public function set(string $option, mixed $value): void
    {
        $this->manager->set($option, $value, $this->owner);
    }

    public function has(string $option): bool
    {
        return $this->manager->has($option, $this->owner);
    }

    public function forget(string $option): void
    {
        $this->manager->forget($option, $this->owner);
    }

    public function reset(string $option): void
    {
        $this->manager->reset($option, $this->owner);
    }

    public function remember(string $option, Closure $callback): mixed
    {
        return $this->manager->remember($option, $callback, $this->owner);
    }

    /**
     * @param  array<int, string>  $options
     * @return array<string, mixed>
     */
    public function many(array $options): array
    {
        return $this->manager->many($options, $this->owner);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        $this->manager->setMany($values, $this->owner);
    }

    /**
     * @return Collection<string, mixed>
     */
    public function all(): Collection
    {
        return $this->manager->all($this->owner);
    }

    private function resolveOption(string $option): BaseOption
    {
        $instance = $this->manager->resolve($option, $this->owner);

        if (! $instance instanceof BaseOption) {
            throw InvalidOptionClassName::for($option);
        }

        return $instance;
    }
}
