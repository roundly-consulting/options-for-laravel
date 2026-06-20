<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts a value at rest. The value is first serialized (to JSON for
 * collection/array casts, otherwise to a string), then encrypted; reads decrypt
 * and then rehydrate according to the declared inner cast.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
final class EncryptedCast implements CastsAttributes
{
    /**
     * @param  CastsAttributes<mixed, mixed>|string  $inner
     */
    public function __construct(private readonly CastsAttributes|string $inner = 'string') {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $decrypted = Crypt::decryptString((string) $value);

        $customInner = $this->customInner();

        if ($customInner !== null) {
            return $customInner->get($model, $key, $decrypted, [$key => $decrypted] + $attributes);
        }

        return $this->fromSerialized($decrypted);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $customInner = $this->customInner();

        if ($customInner !== null) {
            $result = $customInner->set($model, $key, $value, $attributes);
            $serialized = is_array($result) ? ($result[$key] ?? null) : $result;
        } else {
            $serialized = $this->toSerialized($value);
        }

        if ($serialized === null) {
            return null;
        }

        return Crypt::encryptString((string) $serialized);
    }

    private function toSerialized(mixed $value): string
    {
        if ($this->isArrayCast()) {
            $payload = $value instanceof Collection ? $value->toArray() : $value;

            return json_encode($payload, JSON_THROW_ON_ERROR);
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function fromSerialized(string $value): mixed
    {
        if (! $this->isArrayCast()) {
            return $value;
        }

        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return $this->inner === 'collection' ? new Collection($decoded) : $decoded;
    }

    private function isArrayCast(): bool
    {
        return in_array($this->inner, ['array', 'json', 'collection'], true);
    }

    /**
     * @return CastsAttributes<mixed, mixed>|null
     */
    private function customInner(): ?CastsAttributes
    {
        if ($this->inner instanceof CastsAttributes) {
            return $this->inner;
        }

        [$class, $argument] = array_pad(explode(':', $this->inner, 2), 2, null);

        if (! class_exists($class) || ! is_subclass_of($class, CastsAttributes::class)) {
            return null;
        }

        /** @var CastsAttributes<mixed, mixed> */
        return $argument === null ? new $class : new $class($argument);
    }
}
