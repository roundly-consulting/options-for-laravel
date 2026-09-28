<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

/**
 * @internal what one option scope holds in storage: whether a row exists and
 * its raw `value` column string (ciphertext for an encrypted option). This —
 * never the cast value — is what the caches keep, so a read always runs the
 * cast, no plaintext reaches the persistent store and no object has to
 * survive a hardened unserialize().
 */
final readonly class StoredValue
{
    public function __construct(
        public bool $exists,
        public ?string $raw = null,
    ) {}

    public static function missing(): self
    {
        return new self(false);
    }

    /**
     * The persistent-cache payload: scalars only.
     *
     * @return array{exists: bool, raw: ?string}
     */
    public function toCache(): array
    {
        return ['exists' => $this->exists, 'raw' => $this->raw];
    }

    /**
     * Rebuild from a persistent-cache payload; null for anything else (an
     * entry written by an older layout, or a corrupted one), so it is re-read.
     */
    public static function fromCache(mixed $payload): ?self
    {
        if (! is_array($payload) || ! is_bool($payload['exists'] ?? null)) {
            return null;
        }

        $raw = $payload['raw'] ?? null;

        if ($raw !== null && ! is_string($raw)) {
            return null;
        }

        return new self($payload['exists'], $payload['exists'] ? $raw : null);
    }
}
