<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\DataTransferObjects;

final readonly class GroupDefinition
{
    /**
     * @param  list<OptionDefinition>  $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $description,
        public array $options,
    ) {}
}
