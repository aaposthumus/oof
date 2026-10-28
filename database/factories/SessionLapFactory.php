<?php

namespace Database\Factories;

use App\Models\RaceSession;
use App\Models\SessionLap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionLap>
 */
class SessionLapFactory extends Factory
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
            'lap_number' => fake()->numberBetween(1, 70),
            'duration_millis' => fake()->numberBetween(70_000, 110_000),
            'is_pit_out_lap' => false,
        ];
    }
}
