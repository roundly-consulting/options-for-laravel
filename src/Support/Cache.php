<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use Closure;

/**
 * @internal the in-request memo of stored option values. Bound `scoped` in the
 * container, so it lives for one request or one queued job: Laravel drops it
 * between jobs and Octane requests, and the provider also drops it on
 * JobProcessing and Octane's RequestReceived. Cross-process freshness comes from
 * the persistent cache, which every write updates.
 */
final class Cache
{
    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    /**
     * The persistent cache generation this request reads and writes under.
     */
    private ?string $generation = null;

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    public function put(string $key, mixed $value): mixed
    {
        $this->cache[$key] = $value;

        return $value;
    }

    public function get(string $key): mixed
    {
        return $this->cache[$key];
    }

    public function forget(string $key): void
    {
        unset($this->cache[$key]);
    }

    /**
     * @param  Closure(): string  $resolve
     */
    public function generation(Closure $resolve): string
    {
        return $this->generation ??= $resolve();
    }

    public function flush(): void
    {
        $this->cache = [];
        $this->generation = null;
    }
}
