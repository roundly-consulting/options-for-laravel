<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache as CacheManager;
use Illuminate\Support\Str;

/**
 * Persistent cache layer for stored option values, sitting on top of the
 * in-request memo cache. It keeps each scope's raw column value (a
 * {@see StoredValue}), never the cast value: reads cast on the way out, an
 * encrypted option stays ciphertext here, and nothing but scalars is
 * serialized. Tag-aware when the underlying store supports tags.
 */
final class OptionStore
{
    /**
     * The cache fingerprint of one option key in one scope (null owner = global).
     */
    public static function fingerprint(string $key, ?string $ownerType = null, int|string|null $ownerId = null): string
    {
        return "options:{$key}:".self::scope($ownerType, $ownerId);
    }

    /**
     * One owner scope as a fixed-width token (`global` without an owner). The
     * type is length-prefixed so no (type, id) pair can spell another one, as
     * `team1` + `3` and `team` + `13` would when simply concatenated.
     */
    public static function scope(?string $ownerType = null, int|string|null $ownerId = null): string
    {
        if ($ownerType === null) {
            return 'global';
        }

        return md5(strlen($ownerType).':'.$ownerType.'|'.$ownerId);
    }

    public function isEnabled(): bool
    {
        return (bool) config('options.cache.enabled', true);
    }

    /**
     * Resolve the stored value, caching it persistently when enabled. The
     * closure provides the fresh value (a DB read).
     *
     * @param  Closure(): StoredValue  $callback
     */
    public function remember(string $fingerprint, Closure $callback): StoredValue
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $repository = $this->repository();
        $cacheKey = $this->cacheKey($fingerprint);

        $cached = StoredValue::fromCache($repository->get($cacheKey));

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();

        $this->putValue($repository, $cacheKey, $value);

        return $value;
    }

    public function put(string $fingerprint, StoredValue $value): void
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

    /**
     * Empty the option cache on any store: every key embeds the current
     * generation, so moving to a new one orphans all old entries at once (they
     * expire by TTL). A taggable store also purges them right away.
     */
    public function flush(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $repository = $this->baseRepository();

        $this->newGeneration($repository);

        if ($this->supportsTags($repository)) {
            $repository->tags([$this->tag()])->flush();
        }
    }

    private function putValue(Repository $repository, string $cacheKey, StoredValue $value): void
    {
        $ttl = config('options.cache.ttl', 3600);

        if ($ttl === null) {
            $repository->forever($cacheKey, $value->toCache());

            return;
        }

        $repository->put($cacheKey, $value->toCache(), (int) $ttl);
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
        return $this->prefix().':'.$this->generation().':'.$fingerprint;
    }

    /**
     * The generation, read once per request or job (memoised alongside the
     * values), so a flush in another process is seen from its next one on.
     */
    private function generation(): string
    {
        return app(Cache::class)->generation(function (): string {
            $repository = $this->baseRepository();
            $generation = $repository->get($this->generationKey());

            return is_string($generation) && $generation !== ''
                ? $generation
                : $this->newGeneration($repository);
        });
    }

    private function newGeneration(Repository $repository): string
    {
        // Random rather than incremented: never reuses an older generation after
        // an eviction, and needs no atomic increment (the database store has none
        // for a missing key).
        $generation = Str::random(16);

        $repository->forever($this->generationKey(), $generation);

        return $generation;
    }

    private function generationKey(): string
    {
        return $this->prefix().':generation';
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
