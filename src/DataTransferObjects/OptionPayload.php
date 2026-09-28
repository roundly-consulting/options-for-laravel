<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\DataTransferObjects;

use JsonException;
use RoundlyConsulting\Options\Exceptions\InvalidOptionPayload;

/**
 * One stored option as exported/imported: the raw stored value in its scope
 * (`ownerType`/`ownerId` null = global; rows missing either are global). `ownerId` follows `options.key_type`,
 * so it is an int for bigint owners and a string for uuid/ulid owners.
 */
final readonly class OptionPayload
{
    public function __construct(
        public string $key,
        public mixed $value,
        public ?string $ownerType = null,
        public int|string|null $ownerId = null,
    ) {}

    /**
     * A decoded export row (`key`, `value`, `owner_type`, `owner_id`).
     *
     * @param  array<mixed>  $row
     *
     * @throws InvalidOptionPayload
     */
    public static function fromArray(array $row): self
    {
        if (! isset($row['key']) || ! is_string($row['key'])) {
            throw InvalidOptionPayload::message('Each option row requires a string "key".');
        }

        $ownerType = $row['owner_type'] ?? null;
        $ownerType = is_string($ownerType) && $ownerType !== '' ? $ownerType : null;
        $ownerId = $row['owner_id'] ?? null;
        $ownerId = match (true) {
            is_int($ownerId) => $ownerId,
            is_string($ownerId) && ctype_digit($ownerId) => (int) $ownerId,
            is_string($ownerId) && $ownerId !== '' => $ownerId,
            default => null,
        };

        // A scope is an owner type AND id, or neither (global).
        $owned = $ownerType !== null && $ownerId !== null;

        return new self(
            key: $row['key'],
            value: $row['value'] ?? null,
            ownerType: $owned ? $ownerType : null,
            ownerId: $owned ? $ownerId : null,
        );
    }

    /**
     * Payloads from rows, passing payloads through.
     *
     * @param  array<mixed>  $rows
     * @return list<self>
     *
     * @throws InvalidOptionPayload
     */
    public static function list(array $rows): array
    {
        $payloads = [];

        foreach ($rows as $row) {
            $payloads[] = match (true) {
                $row instanceof self => $row,
                is_array($row) => self::fromArray($row),
                default => throw InvalidOptionPayload::message('Each option row must be an array or an OptionPayload.'),
            };
        }

        return $payloads;
    }

    /**
     * Payloads from a JSON export (an array of rows).
     *
     * @return list<self>
     *
     * @throws InvalidOptionPayload
     */
    public static function listFromJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidOptionPayload::message('Invalid JSON payload: '.$exception->getMessage());
        }

        if (! is_array($decoded)) {
            throw InvalidOptionPayload::message('Expected a JSON array of option rows.');
        }

        return self::list($decoded);
    }

    /**
     * @return array{key: string, value: mixed, owner_type: ?string, owner_id: int|string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
        ];
    }
}
