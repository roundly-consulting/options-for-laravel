<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Stringable;
use UnitEnum;

/**
 * @internal converts an option value to and from the raw string the `value`
 * column holds, through any Eloquent cast: a cast string, handled by a probe
 * model exactly as Eloquent would, or a CastsAttributes instance, called
 * directly because Eloquent's mergeCasts() only takes Stringable objects.
 */
final class ValueCaster
{
    /**
     * The raw column string for a value — what a read of the saved row returns.
     *
     * @param  CastsAttributes<mixed, mixed>|string  $cast
     */
    public static function serialize(Model $model, string $key, CastsAttributes|string $cast, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($cast instanceof CastsAttributes) {
            $result = $cast->set($model, $key, $value, $model->getAttributes());

            return self::normalize($model, is_array($result) ? ($result[$key] ?? null) : $result);
        }

        $probe = self::probe($model, $key, $cast);
        $probe->setAttribute($key, $value);

        return self::normalize($probe, $probe->getAttributes()[$key] ?? null);
    }

    /**
     * The cast value for a raw column string.
     *
     * @param  CastsAttributes<mixed, mixed>|string  $cast
     */
    public static function hydrate(Model $model, string $key, CastsAttributes|string $cast, ?string $raw): mixed
    {
        if ($cast instanceof CastsAttributes) {
            return $cast->get($model, $key, $raw, [$key => $raw]);
        }

        $probe = self::probe($model, $key, $cast);
        $probe->setRawAttributes([$key => $raw]);

        return $probe->getAttribute($key);
    }

    private static function probe(Model $model, string $key, string $cast): Model
    {
        $probe = $model->newInstance();
        $probe->mergeCasts([$key => $cast]);

        return $probe;
    }

    /**
     * Mirror what the connection binds (bools as 0/1, dates in the grammar's
     * format) so the string equals what the database hands back.
     */
    private static function normalize(Model $model, mixed $raw): ?string
    {
        return match (true) {
            $raw === null => null,
            is_string($raw) => $raw,
            is_bool($raw) => $raw ? '1' : '0',
            is_int($raw), is_float($raw) => (string) $raw,
            $raw instanceof BackedEnum => (string) $raw->value,
            $raw instanceof UnitEnum => $raw->name,
            $raw instanceof DateTimeInterface => (string) $model->fromDateTime($raw),
            $raw instanceof Stringable => (string) $raw,
            default => json_encode($raw, JSON_THROW_ON_ERROR),
        };
    }
}
