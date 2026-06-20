<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\Option;

/**
 * @extends Factory<Option>
 */
final class OptionFactory extends Factory
{
    protected $model = Option::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => null,
            'owner_type' => null,
            'key' => $this->faker->unique()->word(),
            'value' => $this->faker->word(),
            'meta' => null,
        ];
    }

    public function forOwner(Model $owner): self
    {
        return $this->state(fn (): array => [
            'owner_id' => $owner->getKey(),
            'owner_type' => $owner->getMorphClass(),
        ]);
    }

    public function global(): self
    {
        return $this->state(fn (): array => [
            'owner_id' => null,
            'owner_type' => null,
        ]);
    }

    public function value(mixed $value): self
    {
        return $this->state(fn (): array => ['value' => $value]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        return $this->state(fn (): array => ['meta' => $meta]);
    }
}
