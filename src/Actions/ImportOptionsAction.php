<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Actions;

use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Exceptions\InvalidOptionPayload;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionModel;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Support\StoredValue;
use Throwable;

/**
 * Upserts exported payloads by scope + key, in one transaction, and caches each
 * imported value as a write does. Reach it through `Options::import()`.
 */
final readonly class ImportOptionsAction
{
    public function __construct(private OptionStore $store) {}

    /**
     * All or nothing: every value is checked before the first write, and the
     * writes share a transaction.
     *
     * @param  list<OptionPayload>  $payloads
     *
     * @throws InvalidOptionPayload
     */
    public function execute(array $payloads): int
    {
        $rows = array_map(
            static fn (OptionPayload $payload): array => [$payload, OptionPayload::columnValue($payload->key, $payload->value)],
            $payloads,
        );

        $model = OptionModel::class();
        $memo = app(Cache::class);

        try {
            (new $model)->getConnection()->transaction(function () use ($model, $rows, $memo): void {
                $deletedAt = (new $model)->getDeletedAtColumn();

                foreach ($rows as [$payload, $raw]) {
                    // withTrashed: a forgotten row is revived rather than duplicated,
                    // which the unique (owner_scope, key) index would refuse anyway.
                    $model::query()->withTrashed()->updateOrCreate(
                        [
                            'key' => $payload->key,
                            'owner_type' => $payload->ownerType,
                            'owner_id' => $payload->ownerId,
                        ],
                        ['value' => $raw, $deletedAt => null],
                    );

                    $fingerprint = OptionStore::fingerprint($payload->key, $payload->ownerType, $payload->ownerId);
                    $stored = new StoredValue(true, $raw);

                    // Like a write: this request reads the imported value at once; the
                    // shared entry is dropped now and refilled once the import commits,
                    // so a reader that read the old row cannot cache it back.
                    $memo->put($fingerprint, $stored);
                    $this->store->forget($fingerprint);
                    OptionModel::afterCommit(fn () => $this->store->put($fingerprint, $stored));
                }
            });
        } catch (Throwable $exception) {
            // Rolled back: what this request memoised for the import never happened.
            $memo->flush();

            throw $exception;
        }

        return count($rows);
    }
}
