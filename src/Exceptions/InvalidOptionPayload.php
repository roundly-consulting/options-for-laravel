<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Exceptions;

final class InvalidOptionPayload extends OptionException
{
    public static function message(string $message): self
    {
        return new self($message);
    }
}
