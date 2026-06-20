<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Exceptions;

use Exception;

final class InvalidOptionClassName extends Exception
{
    public static function for(string $class): self
    {
        return new self("Invalid class name provided for option selection. [{$class}]");
    }
}
