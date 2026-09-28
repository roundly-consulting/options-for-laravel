<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Options\Database\Factories\OptionFactory;
use RoundlyConsulting\Options\Support\OptionStore;

/**
 * @property int $id
 * @property int|string|null $owner_id
 * @property string|null $owner_type
 * @property string $owner_scope
 * @property string $key
 * @property string|null $value
 * @property Collection<array-key, mixed>|null $meta
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 *
 * Deliberately not final: `options.model` documents swapping in a host model
 * that extends this one.
 */
class Option extends Model
{
    /** @use HasFactory<OptionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * Every save derives `owner_scope` from the owner columns: one value per
     * scope that the unique (owner_scope, key) index can hold to one row.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(static function (Option $option): void {
            $option->owner_scope = OptionStore::scope($option->owner_type, $option->owner_id);
        });
    }

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

    protected static function newFactory(): OptionFactory
    {
        return OptionFactory::new();
    }
}
