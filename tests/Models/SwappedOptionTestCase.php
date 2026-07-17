<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Models;

use RoundlyConsulting\Options\Tests\TestCase;

/**
 * The suite's base case with `options.model` already pointed at {@see CustomOption} BEFORE
 * the providers boot.
 *
 * Boot order is the whole point: the provider hangs its OptionSet/OptionForgotten listeners
 * and the config bridge on whatever `options.model` names at boot. A `config()->set()`
 * inside the test body reads back correctly but leaves every listener on the packaged
 * Option — precisely the shape that let media #28 ship, and precisely what the existing
 * runtime-`set` tests in `tests/Support/OptionModelTest.php` cannot catch (they assert the
 * *resolver*, which is a narrower and legitimate claim; they are kept for that).
 *
 * Note the `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
 * the base's `app.key`, and every encrypted option would fail for a reason that looks
 * nothing like the cause. That is the same decapitation an un-parented `defineEnvironment()`
 * override causes one level up.
 *
 * @see TestCase
 */
abstract class SwappedOptionTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'options.model' => CustomOption::class,
        ]);
    }
}
