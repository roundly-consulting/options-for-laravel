<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ReflectionEnum;

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

        $backing = $this->backingValue($value);

        // A value that cannot be this enum's backing type is not a case either.
        return $backing === null ? null : ($this->enum)::tryFrom($backing);
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

    /**
     * The stored value as the enum's backing type. The `value` column is text,
     * so an int-backed enum comes back as a numeric string that `tryFrom()`
     * would reject with a TypeError under strict types.
     */
    private function backingValue(mixed $value): int|string|null
    {
        if (! is_scalar($value)) {
            return null;
        }

        if ((string) (new ReflectionEnum($this->enum))->getBackingType() !== 'int') {
            return (string) $value;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);

        return $int === false ? null : $int;
    }
}
