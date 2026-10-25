<?php

namespace Database\Factories;

use App\Models\Connector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connector>
 */
class ConnectorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->lexify('connector-??????'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
