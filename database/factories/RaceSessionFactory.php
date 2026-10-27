<?php

namespace Database\Factories;

use App\Enums\SessionType;
use App\Models\RaceSession;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RaceSession>
 */
class RaceSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'type' => SessionType::Race,
        ];
    }
}
