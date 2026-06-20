<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Exceptions;

final class InvalidOptionGroup extends OptionException
{
    public static function for(string $group): self
    {
        return new self("Invalid option group [{$group}]; expected an OptionGroup subclass.");
    }

    public static function unknownKey(string $group, string $key): self
    {
        return new self("Unknown option key [{$key}] for group [{$group}].");
    }
}
