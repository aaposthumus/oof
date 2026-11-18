<?php

namespace Database\Factories;

use App\Models\DriverEntry;
use App\Models\PracticeResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeResult>
 */
class PracticeResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_entry_id' => DriverEntry::factory(),
            'position' => 1,
            'best_lap_millis' => 90_000,
            'laps' => 25,
        ];
    }
}
