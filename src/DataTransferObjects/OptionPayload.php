<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\DataTransferObjects;

final readonly class OptionPayload
{
    public function __construct(
        public string $key,
        public mixed $value,
        public ?string $ownerType = null,
        public ?int $ownerId = null,
    ) {}

    /**
     * @return array{key: string, value: mixed, owner_type: ?string, owner_id: ?int}
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
