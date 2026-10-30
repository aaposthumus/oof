<?php

namespace Database\Factories;

use App\Models\Circuit;
use App\Models\Round;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
{
    /**
     * Round numbers handed out, counting up from 100 and wrapping at 250, far
     * from the rounds tests name, so a generated round never clashes with one.
     */
    private static int $generated = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'round_number' => 100 + self::$generated++ % 150,
            'circuit_id' => Circuit::factory(),
            'name' => 'British Grand Prix',
            'short_name' => 'Great Britain',
            'country_name' => 'United Kingdom',
            'country_code' => 'GBR',
            'jolpica_race_name' => 'British Grand Prix',
            'date' => fake()->date(),
        ];
    }
}
