<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonTeam>
 */
class SeasonTeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'team_id' => Team::factory(),
            'jolpica_constructor_id' => fake()->unique()->slug(2),
            'name' => fake()->company(),
        ];
    }
}
