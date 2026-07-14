<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Models;

use RoundlyConsulting\Options\Option;

/**
 * A host model swapped in via `options.model` — the case the package model must
 * stay extendable for.
 */
final class CustomOption extends Option
{
    protected $table = 'options';
}
