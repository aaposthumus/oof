<?php

namespace Database\Factories;

use App\Models\ExcavationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExcavationRun>
 */
class ExcavationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'excavator' => 'fewest_starters',
            'excavator_version' => 1,
            'parameters' => ['seasons' => [], 'from' => null, 'to' => null],
            'started_at' => now(),
            'finished_at' => now(),
            'candidates_found' => 0,
        ];
    }
}
