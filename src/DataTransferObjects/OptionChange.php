<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\Enums\OptionChangeType;
use RoundlyConsulting\Options\OptionInterface;

final readonly class OptionChange
{
    /**
     * @param  class-string<OptionInterface>  $optionClass
     */
    public function __construct(
        public string $key,
        public string $optionClass,
        public OptionChangeType $type,
        public mixed $value,
        public ?Model $owner,
    ) {}
}
