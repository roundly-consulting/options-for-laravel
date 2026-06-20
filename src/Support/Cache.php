<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

final class Cache
{
    private static ?self $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self;
    }

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

    public function flush(): void
    {
        $this->cache = [];
    }
}
