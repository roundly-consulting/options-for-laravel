<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Actions;

use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionModel;
use RoundlyConsulting\Options\Support\OptionStore;

/**
 * Upserts exported payloads by scope + key, then drops the in-request memo and
 * each imported value's persistent cache entry. Reach it through `Options::import()`.
 */
final readonly class ImportOptionsAction
{
    public function __construct(private OptionStore $store) {}

    /**
     * @param  list<OptionPayload>  $payloads
     */
    public function execute(array $payloads): int
    {
        $model = OptionModel::class();

        $count = 0;

        foreach ($payloads as $payload) {
            $model::query()->updateOrCreate(
                [
                    'key' => $payload->key,
                    'owner_type' => $payload->ownerType,
                    'owner_id' => $payload->ownerId,
                ],
                ['value' => $payload->value],
            );

            $this->store->forget(OptionStore::fingerprint($payload->key, $payload->ownerType, $payload->ownerId));
            $count++;
        }

        app(Cache::class)->flush();

        return $count;
    }
}
