<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Exceptions;

final class InvalidOptionObserver extends OptionException
{
    public static function for(string $observer): self
    {
        return new self("Invalid option observer [{$observer}]; expected a Closure or an invokable class-string.");
    }
}
