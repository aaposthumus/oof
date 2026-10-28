<?php

namespace Database\Factories;

use App\Models\RaceSession;
use App\Models\Stint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stint>
 */
class StintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'race_session_id' => RaceSession::factory(),
            'driver_number' => fake()->numberBetween(1, 99),
            'stint_number' => 1,
            'compound' => fake()->randomElement(['SOFT', 'MEDIUM', 'HARD']),
            'lap_start' => 1,
            'lap_end' => fake()->numberBetween(10, 30),
            'tyre_age_at_start' => 0,
        ];
    }
}
