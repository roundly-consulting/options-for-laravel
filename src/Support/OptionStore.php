<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache as CacheManager;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Support\Config;

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
        return Config::boolean('options.cache.enabled', true);
    }

    /**
     * Resolve the stored value, caching it persistently when enabled. The
     * closure provides the fresh value (a DB read). The read is only cached if
     * nothing was cached meanwhile (`add()`): a write that landed between this
     * read and the fill has put a newer value, which an older read must not
     * overwrite.
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

        $repository->add($cacheKey, $value->toCache(), OptionsConfig::cacheTtl());

        return $value;
    }

    public function put(string $fingerprint, StoredValue $value): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $repository = $this->repository();

        foreach ($this->writeKeys($fingerprint) as $cacheKey) {
            $this->putValue($repository, $cacheKey, $value);
        }
    }

    public function forget(string $fingerprint): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $repository = $this->repository();

        foreach ($this->writeKeys($fingerprint) as $cacheKey) {
            $repository->forget($cacheKey);
        }
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
        $ttl = OptionsConfig::cacheTtl();

        if ($ttl === null) {
            $repository->forever($cacheKey, $value->toCache());

            return;
        }

        $repository->put($cacheKey, $value->toCache(), $ttl);
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
        return CacheManager::store(OptionsConfig::cacheStore());
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

    /**
     * The key a read uses: under this request's memoised generation.
     */
    private function cacheKey(string $fingerprint): string
    {
        return $this->keyFor($this->generation(), $fingerprint);
    }

    /**
     * The keys a write must reach: under the generation the store holds now —
     * the one every fresh request reads — and under this request's memoised
     * one, should another process have moved on since this request began.
     *
     * @return list<string>
     */
    private function writeKeys(string $fingerprint): array
    {
        $memoised = $this->generation();
        $current = $this->currentGeneration($this->baseRepository());

        return array_values(array_unique([
            $this->keyFor($current, $fingerprint),
            $this->keyFor($memoised, $fingerprint),
        ]));
    }

    private function keyFor(string $generation, string $fingerprint): string
    {
        return $this->prefix().':'.$generation.':'.$fingerprint;
    }

    /**
     * The generation, read once per request or job (memoised alongside the
     * values), so a flush in another process is seen from its next one on.
     */
    private function generation(): string
    {
        return app(Cache::class)->generation(fn (): string => $this->currentGeneration($this->baseRepository()));
    }

    /**
     * The generation the store holds now. A missing key (first use, a cache
     * clear, an eviction) is minted with add() and read back, so requests that
     * find it missing at the same time adopt one generation — whichever landed
     * first — instead of each keeping its own.
     */
    private function currentGeneration(Repository $repository): string
    {
        $generation = $repository->get($this->generationKey());

        if (is_string($generation) && $generation !== '') {
            return $generation;
        }

        $minted = Str::random(16);

        $repository->add($this->generationKey(), $minted);

        $generation = $repository->get($this->generationKey());

        return is_string($generation) && $generation !== '' ? $generation : $minted;
    }

    private function newGeneration(Repository $repository): void
    {
        // Random rather than incremented: never reuses an older generation after
        // an eviction, and needs no atomic increment (the database store has none
        // for a missing key).
        $repository->forever($this->generationKey(), Str::random(16));
    }

    private function generationKey(): string
    {
        return $this->prefix().':generation';
    }

    private function prefix(): string
    {
        return OptionsConfig::cachePrefix();
    }

    private function tag(): string
    {
        return OptionsConfig::cacheTag();
    }
}
