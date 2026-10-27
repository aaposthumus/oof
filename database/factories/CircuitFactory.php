<?php

namespace Database\Factories;

use App\Models\Circuit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Circuit>
 */
class CircuitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jolpica_circuit_id' => fake()->unique()->slug(2),
            'name' => fake()->city().' Circuit',
            'country' => fake()->country(),
        ];
    }
}
