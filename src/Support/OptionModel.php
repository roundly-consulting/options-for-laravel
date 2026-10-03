<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use RoundlyConsulting\Options\Option;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing stored options from `options.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class OptionModel
{
    /**
     * @return class-string<Option>
     */
    public static function class(): string
    {
        return ModelResolver::for('options.model', Option::class);
    }
}
