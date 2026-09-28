<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\OptionInterface;

/**
 * An OptionInterface that is not a BaseOption: it keeps its own storage.
 */
final class PlainInterfaceOption implements OptionInterface
{
    public static mixed $stored = null;

    public static function for(?Model $owner): static
    {
        return new self;
    }

    public function key(): string
    {
        return 'plain';
    }

    public function readable(): string
    {
        return 'Plain';
    }

    public function value(): mixed
    {
        return self::$stored ?? $this->default();
    }

    public function default(): mixed
    {
        return 'default';
    }

    public function castAs(): string
    {
        return 'string';
    }

    public function set(mixed $value): void
    {
        self::$stored = $value;
    }
}
