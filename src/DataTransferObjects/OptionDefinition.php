<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\DataTransferObjects;

use RoundlyConsulting\Options\OptionInterface;

final readonly class OptionDefinition
{
    /**
     * @param  class-string<OptionInterface>  $optionClass
     */
    public function __construct(
        public string $key,
        public string $optionClass,
        public string $label,
        public ?string $help,
        public ?string $section,
        public int $order,
        public string $type,
        public bool $encrypted,
        public mixed $current,
        public mixed $default,
    ) {}
}
