<?php

namespace Database\Factories;

use App\Enums\OpenF1MatchStatus;
use App\Models\OpenF1SessionDriver;
use App\Models\RaceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenF1SessionDriver>
 */
class OpenF1SessionDriverFactory extends Factory
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
            'driver_number' => fake()->unique()->numberBetween(1, 99),
            'name_acronym' => strtoupper(fake()->lexify('???')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'team_name' => fake()->company(),
            'match_status' => OpenF1MatchStatus::Unmatched,
        ];
    }
}
