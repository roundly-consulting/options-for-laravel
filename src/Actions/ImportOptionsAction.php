<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Actions;

use JsonException;
use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Exceptions\InvalidOptionPayload;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionModel;

final class ImportOptionsAction
{
    /**
     * Import a list of payloads into the store, upserting by scope + key.
     *
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

            $count++;
        }

        Cache::getInstance()->flush();

        return $count;
    }

    /**
     * Import from a decoded array of rows.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function fromArray(array $rows): int
    {
        return $this->execute($this->mapRows($rows));
    }

    /**
     * Import from a JSON string.
     */
    public function fromJson(string $json): int
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidOptionPayload::message('Invalid JSON payload: '.$exception->getMessage());
        }

        if (! is_array($decoded)) {
            throw InvalidOptionPayload::message('Expected a JSON array of option rows.');
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($decoded);

        return $this->fromArray($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<OptionPayload>
     */
    private function mapRows(array $rows): array
    {
        $payloads = [];

        foreach ($rows as $row) {
            if (! isset($row['key']) || ! is_string($row['key'])) {
                throw InvalidOptionPayload::message('Each option row requires a string "key".');
            }

            $ownerType = $row['owner_type'] ?? null;
            $ownerId = $row['owner_id'] ?? null;

            $payloads[] = new OptionPayload(
                key: $row['key'],
                value: $row['value'] ?? null,
                ownerType: is_string($ownerType) ? $ownerType : null,
                ownerId: is_numeric($ownerId) ? (int) $ownerId : null,
            );
        }

        return $payloads;
    }
}
