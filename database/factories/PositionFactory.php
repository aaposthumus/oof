<?php

namespace Database\Factories;

use App\Models\Position;
use App\Models\RaceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
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
            'recorded_at' => fake()->dateTime(),
            'position' => fake()->numberBetween(1, 20),
        ];
    }
}
