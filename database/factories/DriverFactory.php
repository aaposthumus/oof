<?php

namespace Database\Factories;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jolpica_driver_id' => fake()->unique()->slug(2),
            'given_name' => fake()->firstName(),
            'family_name' => fake()->lastName(),
        ];
    }
}
