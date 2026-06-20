<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Exceptions;

final class EncryptionNotSupported extends OptionException
{
    public static function forCastInstance(string $option): self
    {
        return new self(
            "Encrypted options must declare castAs() as a string (e.g. 'string', 'collection', or a cast class-string). [{$option}]",
        );
    }
}
