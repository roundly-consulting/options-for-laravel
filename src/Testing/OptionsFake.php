<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Testing;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Enums\OptionChangeType;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\Support\OptionAuthorizer;
use RoundlyConsulting\Options\Support\OptionObservers;

/**
 * `Options::fake()`: an in-memory options store that never touches the database.
 * Every write — the facade, an injected manager, `for()` / `option()` / `key()` /
 * `group()` handles, an option instance (`ThemeOption::for($user)->set()`) and
 * the `HasOptions` trait — lands here, is recorded and fires the registered
 * observers. Authorization still applies; events and caches do not.
 */
final class OptionsFake extends OptionsManager
{
    /**
     * Values per scope (`global` or `morphClass:key`), keyed by option key.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $store = [];

    /**
     * The owner type and id behind each non-global scope, for export().
     *
     * @var array<string, array{0: string, 1: int|string}>
     */
    private array $owners = [];

    /**
     * @var list<array{key: string, value: mixed, owner: ?Model}>
     */
    private array $sets = [];

    /**
     * @var list<array{key: string, owner: ?Model}>
     */
    private array $forgotten = [];

    /**
     * @var list<list<OptionPayload>>
     */
    private array $imports = [];

    public function get(string $option, ?Model $owner = null): mixed
    {
        $instance = $this->resolve($option, $owner);
        $this->guardRead($instance, $owner);
        $key = $instance->key();

        if (array_key_exists($key, $this->store[$this->scope($owner)] ?? [])) {
            return $this->store[$this->scope($owner)][$key];
        }

        return $instance->default();
    }

    public function set(string $option, mixed $value, ?Model $owner = null): void
    {
        $instance = $this->resolve($option, $owner);
        $this->guardWrite($instance, $owner);

        $this->put($instance->key(), $value, $owner?->getMorphClass(), $owner?->getKey());
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

        unset($this->store[$this->scope($owner)][$instance->key()]);
        $this->forgotten[] = ['key' => $instance->key(), 'owner' => $owner];

        app(OptionObservers::class)->dispatch($instance->key(), OptionChangeType::Forgotten, null, $owner);
    }

    /**
     * @return Collection<string, mixed>
     */
    public function all(?Model $owner = null): Collection
    {
        return $this->onlyReadable(collect($this->store[$this->scope($owner)] ?? []), $owner);
    }

    /**
     * The in-memory values as payloads, with the real export's scoping rules.
     *
     * @return list<OptionPayload>
     */
    public function export(?Model $owner = null, bool $globalOnly = false): array
    {
        $payloads = [];

        foreach ($this->store as $scope => $values) {
            if ($owner !== null ? $scope !== $this->scope($owner) : ($globalOnly && $scope !== 'global')) {
                continue;
            }

            [$ownerType, $ownerId] = $this->owners[$scope] ?? [null, null];

            foreach ($values as $key => $value) {
                $payloads[] = new OptionPayload($key, $value, $ownerType, $ownerId);
            }
        }

        return $payloads;
    }

    /**
     * Record the payloads and load them into the in-memory store.
     *
     * @param  array<mixed>|string  $payload
     */
    public function import(array|string $payload): int
    {
        $payloads = $this->payloads($payload);

        foreach ($payloads as $row) {
            $this->put($row->key, $row->value, $row->ownerType, $row->ownerId);
        }

        $this->imports[] = $payloads;

        return count($payloads);
    }

    public function flushCache(): void
    {
        // No persistent cache in the fake; nothing to flush.
    }

    public function assertSet(string $option, mixed $value = null, ?Model $owner = null): void
    {
        $key = $this->resolve($option, $owner)->key();

        $matched = collect($this->sets)->contains(fn (array $record): bool => $record['key'] === $key
            && $this->ownersMatch($record['owner'], $owner)
            && ($value === null || $record['value'] === $value));

        Assert::assertTrue($matched, "Failed asserting that option [{$key}] was set.");
    }

    public function assertNothingSet(): void
    {
        Assert::assertSame([], $this->sets, 'Failed asserting that no options were set.');
    }

    public function assertForgotten(string $option, ?Model $owner = null): void
    {
        $key = $this->resolve($option, $owner)->key();

        $matched = collect($this->forgotten)->contains(
            fn (array $record): bool => $record['key'] === $key && $this->ownersMatch($record['owner'], $owner),
        );

        Assert::assertTrue($matched, "Failed asserting that option [{$key}] was forgotten.");
    }

    public function assertNothingForgotten(): void
    {
        Assert::assertSame([], $this->forgotten, 'Failed asserting that no options were forgotten.');
    }

    /**
     * @param  (Closure(list<OptionPayload>): bool)|null  $callback
     */
    public function assertImported(?Closure $callback = null): void
    {
        $matched = array_filter($this->imports, static fn (array $payloads): bool => $callback === null || $callback($payloads) === true);

        Assert::assertNotEmpty($matched, 'Failed asserting that options were imported.');
    }

    public function assertNothingImported(): void
    {
        Assert::assertSame([], $this->imports, 'Failed asserting that no options were imported.');
    }

    private function put(string $key, mixed $value, ?string $ownerType, int|string|null $ownerId): void
    {
        $scope = $ownerType === null || $ownerId === null ? 'global' : $ownerType.':'.$ownerId;

        if ($scope !== 'global') {
            $this->owners[$scope] = [$ownerType, $ownerId];
        }

        $this->store[$scope][$key] = $value;
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
