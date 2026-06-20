<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

interface OptionInterface
{
    public static function for(?Model $owner): static;

    public function key(): string;

    public function readable(): string;

    public function value(): mixed;

    public function default(): mixed;

    /**
     * @return CastsAttributes<mixed, mixed>|string
     */
    public function castAs(): string|CastsAttributes;

    public function set(mixed $value): void;
}
