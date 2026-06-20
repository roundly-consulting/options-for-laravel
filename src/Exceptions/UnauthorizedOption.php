<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Exceptions;

final class UnauthorizedOption extends OptionException
{
    public static function read(string $key): self
    {
        return new self("Not authorized to read option [{$key}].");
    }

    public static function write(string $key): self
    {
        return new self("Not authorized to write option [{$key}].");
    }
}
