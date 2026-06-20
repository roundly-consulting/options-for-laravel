<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Testing;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\Enums\OptionChangeType;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\Support\OptionAuthorizer;
use RoundlyConsulting\Options\Support\OptionObservers;

/**
 * In-memory options manager for tests. Never touches the database.
 */
final class FakeOptionsManager extends OptionsManager
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $store = [];

    /**
     * @var array<int, array{key: string, value: mixed, owner: ?Model}>
     */
    private array $sets = [];

    /**
     * @var array<int, array{key: string, owner: ?Model}>
     */
    private array $forgotten = [];

    public function get(string $option, ?Model $owner = null): mixed
    {
        $instance = $this->resolve($option, $owner);
        $this->guardRead($instance, $owner);
        $scope = $this->scope($owner);
        $key = $instance->key();

        if (array_key_exists($key, $this->store[$scope] ?? [])) {
            return $this->store[$scope][$key];
        }

        return $instance->default();
    }

    public function set(string $option, mixed $value, ?Model $owner = null): void
    {
        $instance = $this->resolve($option, $owner);
        $this->guardWrite($instance, $owner);
        $scope = $this->scope($owner);

        $this->store[$scope][$instance->key()] = $value;
        $this->sets[] = ['key' => $instance->key(), 'value' => $value, 'owner' => $owner];

        app(OptionObservers::class)->dispatch($instance->key(), OptionChangeType::Set, $value, $owner);
    }

    public function has(string $option, ?Model $owner = null): bool
    {
        $instance = $this->resolve($option, $owner);
        $this->guardRead($instance, $owner);

        return array_key_exists($instance->key(), $this->store[$this->scope($owner)] ?? []);
    }

    public function forget(string $option, ?Model $owner = null): void
    {
        $instance = $this->resolve($option, $owner);
        $this->guardWrite($instance, $owner);
        $scope = $this->scope($owner);

        unset($this->store[$scope][$instance->key()]);
        $this->forgotten[] = ['key' => $instance->key(), 'owner' => $owner];

        app(OptionObservers::class)->dispatch($instance->key(), OptionChangeType::Forgotten, null, $owner);
    }

    public function reset(string $option, ?Model $owner = null): void
    {
        $this->forget($option, $owner);
    }

    public function remember(string $option, Closure $callback, ?Model $owner = null): mixed
    {
        if ($this->has($option, $owner)) {
            return $this->get($option, $owner);
        }

        $value = $callback();

        $this->set($option, $value, $owner);

        return $value;
    }

    /**
     * @return Collection<string, mixed>
     */
    public function all(?Model $owner = null): Collection
    {
        return collect($this->store[$this->scope($owner)] ?? []);
    }

    public function flushCache(): void
    {
        // No persistent cache in the fake; nothing to flush.
    }

    public function assertSet(string $option, mixed $value = null, ?Model $owner = null): void
    {
        $key = $this->resolve($option, $owner)->key();

        $matched = collect($this->sets)->contains(function (array $record) use ($key, $value, $owner): bool {
            if ($record['key'] !== $key) {
                return false;
            }

            if (! $this->ownersMatch($record['owner'], $owner)) {
                return false;
            }

            return $value === null || $record['value'] === $value;
        });

        Assert::assertTrue($matched, "Failed asserting that option [{$key}] was set.");
    }

    public function assertForgotten(string $option, ?Model $owner = null): void
    {
        $key = $this->resolve($option, $owner)->key();

        $matched = collect($this->forgotten)->contains(
            fn (array $record): bool => $record['key'] === $key && $this->ownersMatch($record['owner'], $owner),
        );

        Assert::assertTrue($matched, "Failed asserting that option [{$key}] was forgotten.");
    }

    public function assertNothingSet(): void
    {
        Assert::assertSame([], $this->sets, 'Failed asserting that no options were set.');
    }

    private function guardRead(mixed $instance, ?Model $owner): void
    {
        if ($instance instanceof BaseOption) {
            app(OptionAuthorizer::class)->read($instance, $owner);
        }
    }

    private function guardWrite(mixed $instance, ?Model $owner): void
    {
        if ($instance instanceof BaseOption) {
            app(OptionAuthorizer::class)->write($instance, $owner);
        }
    }

    private function ownersMatch(?Model $a, ?Model $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return $a->getMorphClass() === $b->getMorphClass()
            && $a->getKey() === $b->getKey();
    }

    private function scope(?Model $owner): string
    {
        if ($owner === null) {
            return 'global';
        }

        return $owner->getMorphClass().':'.$owner->getKey();
    }
}
