<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\OptionsManager;

/**
 * Bridges DB-backed options onto the Laravel config repository.
 */
final class ConfigBridge
{
    /**
     * Map of config keys to canonical option class-strings.
     *
     * @var array<string, class-string<OptionInterface>>
     */
    private array $mappings = [];

    /**
     * Captured original config values, indexed by config key.
     *
     * @var array<string, mixed>
     */
    private array $originals = [];

    public function __construct(private readonly OptionsManager $manager) {}

    /**
     * Seed the map from the configured config_overrides array.
     *
     * @param  array<string, class-string<OptionInterface>|string>  $map
     */
    public function seedFromConfig(array $map): void
    {
        $this->mappings = [];
        $this->originals = [];

        foreach ($map as $configKey => $option) {
            $this->mappings[$configKey] = $this->manager->resolveClass($option);
        }
    }

    /**
     * Register (or override) a single mapping and apply it immediately.
     *
     * @param  class-string<OptionInterface>  $option
     */
    public function add(string $configKey, string $option): void
    {
        $this->mappings[$configKey] = $option;

        $this->applyKey($configKey, $option);
    }

    /**
     * The active config override map.
     *
     * @return array<string, class-string<OptionInterface>>
     */
    public function mappings(): array
    {
        return $this->mappings;
    }

    public function hasMappings(): bool
    {
        return $this->mappings !== [];
    }

    /**
     * Re-read every mapped option and push values into config().
     */
    public function apply(): void
    {
        foreach ($this->mappings as $configKey => $option) {
            $this->applyKey($configKey, $option);
        }
    }

    /**
     * The config key mapped to the given option key (if any).
     */
    public function configKeyForOptionKey(string $optionKey): ?string
    {
        foreach ($this->mappings as $configKey => $option) {
            if ($option::for(null)->key() === $optionKey) {
                return $configKey;
            }
        }

        return null;
    }

    /**
     * React to a live option change by re-applying or reverting the mapped key.
     */
    public function syncOptionKey(string $optionKey): void
    {
        if (! config('options.config_overrides_live', false)) {
            return;
        }

        $configKey = $this->configKeyForOptionKey($optionKey);

        if ($configKey === null) {
            return;
        }

        $this->applyKey($configKey, $this->mappings[$configKey], revertWhenUnset: true);
    }

    /**
     * Apply a single mapping, skipping keys whose option has no stored value.
     *
     * @param  class-string<OptionInterface>  $option
     */
    private function applyKey(string $configKey, string $option, bool $revertWhenUnset = false): void
    {
        if (! array_key_exists($configKey, $this->originals)) {
            $this->originals[$configKey] = config($configKey);
        }

        $instance = $this->manager->resolveOptionInstance($option);

        if (! $instance->has()) {
            if ($revertWhenUnset) {
                config()->set($configKey, $this->originals[$configKey]);
            }

            return;
        }

        config()->set($configKey, $instance->value());
    }
}
