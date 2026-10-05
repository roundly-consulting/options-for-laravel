<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ReflectionEnum;
use RoundlyConsulting\Options\Exceptions\InvalidOptionPayload;

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
     * Stores a case's backing value. Anything that is not a case of this enum —
     * a misspelt string, another enum's case — is refused rather than stored,
     * since a read could only turn it into `null`.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidOptionPayload
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int|string|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value instanceof $this->enum ? $value->value : throw $this->notACase($value);
        }

        $backing = $this->backingValue($value);
        $case = $backing === null ? null : ($this->enum)::tryFrom($backing);

        if ($case === null) {
            throw $this->notACase($value);
        }

        return $case->value;
    }

    private function notACase(mixed $value): InvalidOptionPayload
    {
        $shown = match (true) {
            $value instanceof BackedEnum => $value::class.'::'.$value->name,
            is_scalar($value) => (string) $value,
            default => get_debug_type($value),
        };

        return InvalidOptionPayload::message("[{$shown}] is not a case of [{$this->enum}].");
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
