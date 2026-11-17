<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\OofscoreStanding;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OofscoreStanding>
 */
class OofscoreStandingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $round = Round::factory()->create();

        return [
            'season_id' => $round->season_id,
            'round_id' => $round->id,
            'driver_id' => Driver::factory(),
            'points' => fake()->numberBetween(0, 500),
            'awards_count' => fake()->numberBetween(1, 30),
            'position' => fake()->numberBetween(1, 22),
            'position_change' => null,
        ];
    }
}
