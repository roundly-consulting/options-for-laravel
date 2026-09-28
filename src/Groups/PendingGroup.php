<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Groups;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\DataTransferObjects\GroupDefinition;
use RoundlyConsulting\Options\DataTransferObjects\OptionDefinition;
use RoundlyConsulting\Options\Exceptions\InvalidOptionGroup;
use RoundlyConsulting\Options\OptionsManager;

/**
 * Fluent builder for resolving and writing a setting group.
 */
final class PendingGroup
{
    public function __construct(
        private readonly OptionsManager $manager,
        private readonly OptionGroup $group,
        private ?Model $owner = null,
    ) {}

    /**
     * Scope the group to an owner (chainable).
     */
    public function for(?Model $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * Resolve every option in the group to its current value, keyed by option key.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $values = [];

        foreach ($this->resolveOptions() as $option) {
            $values[$option->key()] = $option->value();
        }

        return $values;
    }

    /**
     * Bulk write, all or nothing (see `Options::setMany()`). Keys may be the
     * option key OR the option class-string.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $byKey = [];
        $byClass = [];

        foreach ($this->group->options() as $class) {
            $option = $this->resolve($class);
            $byKey[$option->key()] = $option::class;
            $byClass[$class] = $option::class;
        }

        $writes = [];

        foreach ($values as $identifier => $value) {
            $class = $byKey[$identifier] ?? $byClass[$identifier] ?? null;

            if ($class === null) {
                throw InvalidOptionGroup::unknownKey($this->group::class, $identifier);
            }

            $writes[$class] = $value;
        }

        $this->manager->setMany($writes, $this->owner);
    }

    /**
     * Introspection for building a UI.
     */
    public function definition(): GroupDefinition
    {
        $definitions = [];

        foreach ($this->resolveOptions() as $option) {
            $definitions[] = new OptionDefinition(
                key: $option->key(),
                optionClass: $option::class,
                label: $option->label(),
                help: $option->help(),
                section: $option->section(),
                order: $option->order(),
                type: $this->typeFor($option),
                encrypted: $option->encrypted(),
                current: $option->value(),
                default: $option->default(),
            );
        }

        return new GroupDefinition(
            key: $this->group->key(),
            label: $this->group->label(),
            description: $this->group->description(),
            options: $definitions,
        );
    }

    /**
     * The resolved member option instances, in order.
     *
     * @return list<BaseOption>
     */
    public function options(): array
    {
        return $this->resolveOptions();
    }

    /**
     * @return list<BaseOption>
     */
    private function resolveOptions(): array
    {
        $options = [];

        foreach ($this->group->options() as $index => $class) {
            $options[] = ['index' => $index, 'option' => $this->resolve($class)];
        }

        usort($options, function (array $a, array $b): int {
            return [$a['option']->order(), $a['index']] <=> [$b['option']->order(), $b['index']];
        });

        return array_map(fn (array $entry): BaseOption => $entry['option'], $options);
    }

    private function resolve(string $class): BaseOption
    {
        $instance = $this->manager->resolve($class, $this->owner);

        if (! $instance instanceof BaseOption) {
            throw InvalidOptionGroup::for($class);
        }

        return $instance;
    }

    private function typeFor(BaseOption $option): string
    {
        $cast = $option->castAs();

        if (is_string($cast)) {
            return $cast;
        }

        return 'custom';
    }
}
