<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Closure;

/**
 * Fluent leaf for a single option in a single owner scope.
 */
final class OptionContext
{
    public function __construct(private readonly BaseOption $option) {}

    public function get(): mixed
    {
        return $this->option->value();
    }

    public function value(): mixed
    {
        return $this->option->value();
    }

    public function set(mixed $value): void
    {
        $this->option->set($value);
    }

    public function has(): bool
    {
        return $this->option->has();
    }

    public function forget(): void
    {
        $this->option->forget();
    }

    public function reset(): void
    {
        $this->option->reset();
    }

    public function default(): mixed
    {
        return $this->option->default();
    }

    public function remember(Closure $callback): mixed
    {
        return $this->option->remember($callback);
    }

    public function readable(): string
    {
        return $this->option->readable();
    }

    public function key(): string
    {
        return $this->option->key();
    }
}
