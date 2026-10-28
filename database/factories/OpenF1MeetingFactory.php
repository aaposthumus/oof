<?php

namespace Database\Factories;

use App\Enums\OpenF1MatchStatus;
use App\Models\OpenF1Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenF1Meeting>
 */
class OpenF1MeetingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_key' => fake()->unique()->numberBetween(1000, 9999),
            'year' => 2026,
            'name' => 'British Grand Prix',
            'circuit_key' => 2,
            'circuit_short_name' => 'Silverstone',
            'date_start' => '2026-07-03 11:30:00',
            'is_cancelled' => false,
            'match_status' => OpenF1MatchStatus::Unmatched,
        ];
    }
}
