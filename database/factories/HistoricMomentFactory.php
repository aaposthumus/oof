<?php

namespace Database\Factories;

use App\Enums\HistoricMomentStatus;
use App\Models\HistoricMoment;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoricMoment>
 */
class HistoricMomentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'moment_candidate_id' => null,
            'round_id' => Round::factory(),
            'date' => fake()->date(),
            'title' => fake()->sentence(),
            'story' => fake()->paragraph(),
            'tags' => [],
            'era' => '2000s',
            'status' => HistoricMomentStatus::Draft,
        ];
    }
}
