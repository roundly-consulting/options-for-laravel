<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\OptionModel;

final class ExportOptionsAction
{
    /**
     * Export stored options as a list of payloads. Without an owner, every
     * stored option (global and owned) is exported.
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

        return array_values($query->get()
            ->map(fn (Option $option): OptionPayload => new OptionPayload(
                key: $option->key,
                value: $option->value,
                ownerType: $option->owner_type,
                ownerId: $option->owner_id,
            ))
            ->all());
    }

    /**
     * Export options as a JSON string.
     */
    public function toJson(?Model $owner = null, bool $globalOnly = false): string
    {
        $payloads = array_map(
            fn (OptionPayload $payload): array => $payload->toArray(),
            $this->execute($owner, $globalOnly),
        );

        return json_encode($payloads, JSON_THROW_ON_ERROR);
    }
}
