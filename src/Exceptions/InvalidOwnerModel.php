<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Exceptions;

final class InvalidOwnerModel extends OptionException
{
    public static function for(string $class): self
    {
        return new self("The owner class is not an Eloquent model. [{$class}]");
    }
}
