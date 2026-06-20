<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache as CacheManager;

/**
 * Persistent cache layer for resolved option values, sitting on top of the
 * in-request memo cache. Tag-aware when the underlying store supports tags.
 */
final class OptionStore
{
    public function isEnabled(): bool
    {
        return (bool) config('options.cache.enabled', true);
    }

    /**
     * Resolve the value, caching it persistently when enabled. The closure
     * provides the fresh value (typically a DB read).
     */
    public function remember(string $fingerprint, Closure $callback): mixed
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $repository = $this->repository();
        $cacheKey = $this->cacheKey($fingerprint);

        if ($repository->has($cacheKey)) {
            return $repository->get($cacheKey);
        }

        $value = $callback();

        $this->putValue($repository, $cacheKey, $value);

        return $value;
    }

    public function put(string $fingerprint, mixed $value): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->putValue($this->repository(), $this->cacheKey($fingerprint), $value);
    }

    public function forget(string $fingerprint): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->repository()->forget($this->cacheKey($fingerprint));
    }

    public function flush(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $repository = $this->baseRepository();

        if ($this->supportsTags($repository)) {
            $repository->tags([$this->tag()])->flush();
        }

        // Non-taggable stores expire by TTL; nothing else to flush safely.
    }

    private function putValue(Repository $repository, string $cacheKey, mixed $value): void
    {
        $ttl = config('options.cache.ttl', 3600);

        if ($ttl === null) {
            $repository->forever($cacheKey, $value);

            return;
        }

        $repository->put($cacheKey, $value, (int) $ttl);
    }

    private function repository(): Repository
    {
        $repository = $this->baseRepository();

        if ($this->supportsTags($repository)) {
            return $repository->tags([$this->tag()]);
        }

        return $repository;
    }

    private function baseRepository(): Repository
    {
        $store = config('options.cache.store');

        return CacheManager::store(is_string($store) ? $store : null);
    }

    private function supportsTags(Repository $repository): bool
    {
        $store = $repository->getStore();

        return method_exists($store, 'tags') && $this->storeSupportsTags($store);
    }

    private function storeSupportsTags(Store $store): bool
    {
        return $store instanceof TaggableStore;
    }

    private function cacheKey(string $fingerprint): string
    {
        return $this->prefix().':'.$fingerprint;
    }

    private function prefix(): string
    {
        $prefix = config('options.cache.prefix', 'options');

        return is_string($prefix) ? $prefix : 'options';
    }

    private function tag(): string
    {
        $tag = config('options.cache.tag', 'options');

        return is_string($tag) ? $tag : 'options';
    }
}
