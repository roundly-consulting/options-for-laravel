<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Actions;

use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionModel;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Support\StoredValue;

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

        $deletedAt = (new $model)->getDeletedAtColumn();

        foreach ($payloads as $payload) {
            // withTrashed: a forgotten row is revived rather than duplicated,
            // which the unique (owner_scope, key) index would refuse anyway.
            $model::query()->withTrashed()->updateOrCreate(
                [
                    'key' => $payload->key,
                    'owner_type' => $payload->ownerType,
                    'owner_id' => $payload->ownerId,
                ],
                ['value' => $payload->value, $deletedAt => null],
            );

            $fingerprint = OptionStore::fingerprint($payload->key, $payload->ownerType, $payload->ownerId);
            $stored = new StoredValue(true, $this->rawValue($payload->value));

            // Like a write: drop the shared entry now, put the imported value once it
            // commits, so a reader that read the old row cannot cache it back.
            $this->store->forget($fingerprint);
            OptionModel::afterCommit(fn () => $this->store->put($fingerprint, $stored));
            $count++;
        }

        app(Cache::class)->flush();

        return $count;
    }

    /**
     * The column string the database holds for an imported scalar.
     */
    private function rawValue(mixed $value): ?string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => null,
        };
    }
}
