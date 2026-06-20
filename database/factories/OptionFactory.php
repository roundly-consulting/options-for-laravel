<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
}
