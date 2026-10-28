<?php

namespace Database\Factories;

use App\Models\RaceControlMessage;
use App\Models\RaceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RaceControlMessage>
 */
class RaceControlMessageFactory extends Factory
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
            'fingerprint' => fake()->unique()->sha1(),
            'issued_at' => fake()->dateTime(),
            'category' => 'Flag',
            'flag' => 'GREEN',
            'scope' => 'Track',
            'message' => 'GREEN LIGHT - PIT EXIT OPEN',
        ];
    }
}
