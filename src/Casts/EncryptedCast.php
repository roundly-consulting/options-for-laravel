<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use RoundlyConsulting\Options\Support\ValueCaster;

/**
 * Encrypts a value at rest. The value is first serialized through the declared
 * inner cast (any Eloquent cast string or a CastsAttributes instance), then
 * encrypted; reads decrypt and rehydrate through the same inner cast, so an
 * encrypted `integer` comes back an int and an encrypted `boolean` a bool.
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

        return ValueCaster::hydrate($model, $key, $this->inner, Crypt::decryptString((string) $value));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $serialized = ValueCaster::serialize($model, $key, $this->inner, $value);

        return $serialized === null ? null : Crypt::encryptString($serialized);
    }
}
