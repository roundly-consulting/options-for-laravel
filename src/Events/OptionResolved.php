<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class OptionResolved
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly ?Model $owner = null,
    ) {}
}
