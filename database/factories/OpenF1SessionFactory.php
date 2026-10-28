<?php

namespace Database\Factories;

use App\Enums\OpenF1MatchStatus;
use App\Models\OpenF1Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenF1Session>
 */
class OpenF1SessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_key' => fake()->unique()->numberBetween(10000, 99999),
            'meeting_key' => fake()->numberBetween(1000, 9999),
            'year' => 2026,
            'name' => 'Race',
            'session_type' => 'Race',
            'date_start' => '2026-07-05 14:00:00',
            'is_cancelled' => false,
            'match_status' => OpenF1MatchStatus::Unmatched,
        ];
    }
}
