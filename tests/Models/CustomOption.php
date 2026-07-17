<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Models;

use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * A host model swapped in via `options.model` — the case the package model must
 * stay extendable for.
 *
 * `CountsCreations` is what makes the swap proof independent of `instanceof`: it counts
 * rows created *as this exact class*, so a flow that resolved the seam correctly for the
 * return value but created the row as the packaged Option (permissions #31 —
 * `static::query()` inside the packaged model) is visible, where an `instanceof` check
 * would pass.
 */
final class CustomOption extends Option
{
    use CountsCreations;

    protected $table = 'options';
}
