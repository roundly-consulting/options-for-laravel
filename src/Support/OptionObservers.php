<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\DataTransferObjects\OptionChange;
use RoundlyConsulting\Options\Enums\OptionChangeType;
use RoundlyConsulting\Options\Exceptions\InvalidOptionObserver;
use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\OptionsManager;

/**
 * Registry of per-option observers, dispatched on set/forget.
 */
final class OptionObservers
{
    /**
     * Observers indexed by the canonical option class-string, in FIFO order.
     *
     * @var array<class-string<OptionInterface>, list<Closure|class-string>>
     */
    private array $byClass = [];

    /**
     * Observers indexed by the option key, in FIFO order.
     *
     * @var array<string, list<Closure|class-string>>
     */
    private array $byKey = [];

    public function __construct(private readonly OptionsManager $manager) {}

    /**
     * Register a callback fired when the given option changes.
     *
     * @param  class-string<OptionInterface>|string  $option
     * @param  Closure|class-string  $callback
     */
    public function observe(string $option, Closure|string $callback): void
    {
        if (is_string($callback) && ! $this->isInvokable($callback)) {
            throw InvalidOptionObserver::for($callback);
        }

        $class = $this->manager->resolveClass($option);
        $key = $class::for(null)->key();

        $this->byClass[$class][] = $callback;
        $this->byKey[$key][] = $callback;
    }

    /**
     * Remove all observers registered for one option (class-string or key).
     *
     * @param  class-string<OptionInterface>|string  $option
     */
    public function forget(string $option): void
    {
        $class = $this->manager->resolveClass($option);
        $key = $class::for(null)->key();

        unset($this->byClass[$class], $this->byKey[$key]);
    }

    /**
     * Remove every registered observer.
     */
    public function flush(): void
    {
        $this->byClass = [];
        $this->byKey = [];
    }

    /**
     * Dispatch the observers registered for the option key.
     */
    public function dispatch(string $key, OptionChangeType $type, mixed $value, ?Model $owner): void
    {
        $callbacks = $this->byKey[$key] ?? [];

        if ($callbacks === []) {
            return;
        }

        $class = $this->classForKey($key);

        $change = new OptionChange(
            key: $key,
            optionClass: $class,
            type: $type,
            value: $value,
            owner: $owner,
        );

        foreach ($callbacks as $callback) {
            $this->invoke($callback, $value, $owner, $change);
        }
    }

    /**
     * Resolve the canonical option class for a key registered through observe().
     *
     * @return class-string<OptionInterface>
     */
    private function classForKey(string $key): string
    {
        foreach (array_keys($this->byClass) as $class) {
            if ($class::for(null)->key() === $key) {
                return $class;
            }
        }

        return $this->manager->resolveClass($key);
    }

    private function invoke(Closure|string $callback, mixed $value, ?Model $owner, OptionChange $change): void
    {
        if ($callback instanceof Closure) {
            $callback($value, $owner, $change);

            return;
        }

        $instance = app()->make($callback);

        $instance($value, $owner, $change);
    }

    private function isInvokable(string $callback): bool
    {
        return class_exists($callback) && method_exists($callback, '__invoke');
    }
}
