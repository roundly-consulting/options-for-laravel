<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict reads of the host's cache settings. An unset key takes its default; a present but
 * invalid value throws {@see InvalidConfigurationException} naming the key — a `cache.ttl`
 * of `abc` used to be cast to 0, so every write expired at once and the cache silently did
 * nothing.
 *
 * @internal
 */
final class OptionsConfig
{
    /**
     * The persistent cache lifetime in seconds, or null to cache forever (an explicit
     * `null`, e.g. `OPTIONS_CACHE_TTL=null`). An absent key means 3600; anything else must
     * be a whole number of at least 1.
     */
    public static function cacheTtl(): ?int
    {
        $ttl = config('options.cache.ttl', 3600);

        return $ttl === null ? null : Config::for(['options.cache.ttl' => $ttl])->integer('options.cache.ttl', 3600, min: 1);
    }

    /** The cache store name, or null for the default store. */
    public static function cacheStore(): ?string
    {
        $store = config('options.cache.store');

        return $store === null ? null : self::string('options.cache.store', $store, '');
    }

    public static function cachePrefix(): string
    {
        return self::string('options.cache.prefix', config('options.cache.prefix'), 'options');
    }

    public static function cacheTag(): string
    {
        return self::string('options.cache.tag', config('options.cache.tag'), 'options');
    }

    private static function string(string $key, mixed $value, string $default): string
    {
        if ($value === null) {
            return $default;
        }

        if (! is_string($value) || trim($value) === '') {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }
}
