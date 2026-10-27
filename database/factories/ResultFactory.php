<?php

namespace Database\Factories;

use App\Models\DriverEntry;
use App\Models\Result;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Result>
 */
class ResultFactory extends Factory
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
            'grid' => 1,
            'position' => 1,
            'position_text' => '1',
            'classification_order' => 1,
            'is_classified' => true,
            'status' => 'Finished',
            'points' => 0,
            'laps_completed' => 60,
        ];
    }

    /**
     * Classified in this position, with this status ("Finished", "+1 Lap", or a retirement reason).
     */
    public function classified(int $position, string $status = 'Finished'): static
    {
        return $this->state(fn () => [
            'position' => $position,
            'position_text' => (string) $position,
            'classification_order' => $position,
            'is_classified' => true,
            'status' => $status,
        ]);
    }

    /**
     * Started but retired without being classified.
     */
    public function retired(string $status = 'Engine'): static
    {
        return $this->state(fn () => [
            'position' => null,
            'position_text' => 'R',
            'classification_order' => 90,
            'is_classified' => false,
            'status' => $status,
            'laps_completed' => 10,
        ]);
    }

    /**
     * Entered but did not start ("W" in the classification).
     */
    public function withdrawn(): static
    {
        return $this->state(fn () => [
            'position' => null,
            'position_text' => 'W',
            'classification_order' => 99,
            'is_classified' => false,
            'status' => 'Withdrew',
            'laps_completed' => 0,
        ]);
    }

    public function fromGrid(int $grid): static
    {
        return $this->state(fn () => ['grid' => $grid]);
    }
}
