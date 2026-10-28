<?php

namespace Database\Factories;

use App\Models\RaceSession;
use App\Models\WeatherSample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeatherSample>
 */
class WeatherSampleFactory extends Factory
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
            'recorded_at' => fake()->dateTime(),
            'air_temperature' => fake()->randomFloat(1, 10, 40),
            'track_temperature' => fake()->randomFloat(1, 15, 55),
            'humidity' => fake()->randomFloat(1, 20, 90),
            'rainfall' => false,
        ];
    }
}
