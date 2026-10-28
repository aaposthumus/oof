<?php

namespace Database\Factories;

use App\Models\PitLaneVisit;
use App\Models\RaceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PitLaneVisit>
 */
class PitLaneVisitFactory extends Factory
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
            'entered_at' => fake()->dateTime(),
            'lane_duration_millis' => fake()->numberBetween(18_000, 30_000),
        ];
    }
}
