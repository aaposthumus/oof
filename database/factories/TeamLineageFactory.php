<?php

namespace Database\Factories;

use App\Models\TeamLineage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamLineage>
 */
class TeamLineageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jolpica_constructor_id' => fake()->unique()->slug(2),
            'from_year' => null,
            'to_year' => null,
            'team_slug' => fake()->unique()->slug(2),
            'note' => null,
        ];
    }
}
