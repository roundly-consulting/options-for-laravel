<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ValueError;

/**
 * Generic backed-enum cast. Pass the enum class as a cast parameter, e.g.
 * `return EnumCast::class.':'.Status::class;` from an option's castAs().
 *
 * @implements CastsAttributes<BackedEnum|null, BackedEnum|int|string|null>
 */
final class EnumCast implements CastsAttributes
{
    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public function __construct(private readonly string $enum) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value;
        }

        $enum = $this->enum;

        try {
            return $enum::from(is_int($value) ? $value : (string) $value);
        } catch (ValueError) {
            return $enum::tryFrom(is_int($value) ? $value : (string) $value);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int|string|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return is_int($value) ? $value : (string) $value;
    }
}
