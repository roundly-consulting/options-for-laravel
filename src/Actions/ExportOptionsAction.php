<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\OptionModel;

/**
 * Exports stored options as raw payloads (encrypted values stay encrypted).
 * Reach it through `Options::export()` / `Options::exportJson()`.
 */
final readonly class ExportOptionsAction
{
    /**
     * With an owner: that owner's options. Without: every stored option
     * (global and owned), or only the global ones with `$globalOnly`.
     *
     * @return list<OptionPayload>
     */
    public function execute(?Model $owner = null, bool $globalOnly = false): array
    {
        $model = OptionModel::class();

        $query = $model::query();

        if ($owner !== null) {
            $query->forOwner($owner);
        } elseif ($globalOnly) {
            $query->forOwner(null);
        }

        return array_values($query->orderBy('id')->get()
            ->map(fn (Option $option): OptionPayload => new OptionPayload(
                key: $option->key,
                value: $option->value,
                ownerType: $option->owner_type,
                ownerId: $option->owner_id,
            ))
            ->all());
    }
}
