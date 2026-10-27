<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use App\Models\SeasonTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverEntry>
 */
class DriverEntryFactory extends Factory
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
            'driver_id' => Driver::factory(),
            'season_team_id' => SeasonTeam::factory(),
            'car_number' => fake()->numberBetween(1, 99),
        ];
    }
}
