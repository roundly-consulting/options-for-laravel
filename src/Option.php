<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Database\Factories\OptionFactory;

/**
 * @property int $id
 * @property int|null $owner_id
 * @property string|null $owner_type
 * @property string $key
 * @property string|null $value
 * @property Collection<array-key, mixed>|null $meta
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class Option extends Model
{
    /** @use HasFactory<OptionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Option>  $query
     * @return Builder<Option>
     */
    public function scopeForOwner(Builder $query, ?Model $owner): Builder
    {
        if (is_null($owner)) {
            return $query->where(function (Builder $query): Builder {
                return $query->whereNull('owner_id')
                    ->whereNull('owner_type');
            });
        }

        return $query->whereMorphedTo('owner', $owner);
    }

    /**
     * Temporarily apply a cast to the `value` attribute for the duration of the callback.
     *
     * @param  CastsAttributes<mixed, mixed>|string  $cast
     */
    public function whileCastingValueAs(string|CastsAttributes $cast, Closure $callback): mixed
    {
        $this->mergeCasts(['value' => $cast]);

        try {
            return $callback();
        } finally {
            unset($this->casts['value']);
        }
    }

    /**
     * @param  CastsAttributes<mixed, mixed>|string  $cast
     */
    public function castValueAs(string|CastsAttributes $cast): mixed
    {
        return $this->whileCastingValueAs(
            cast: $cast,
            callback: fn (): mixed => $this->castAttribute('value', $this->attributes['value'] ?? null),
        );
    }

    protected static function newFactory(): OptionFactory
    {
        return OptionFactory::new();
    }
}
