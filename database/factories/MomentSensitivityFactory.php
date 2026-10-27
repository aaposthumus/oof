<?php

namespace Database\Factories;

use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use App\Models\MomentSensitivity;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MomentSensitivity>
 */
class MomentSensitivityFactory extends Factory
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
            'driver_id' => null,
            'level' => SensitivityLevel::Sensitive,
            'severity' => SensitivitySeverity::Fatal,
            'note' => fake()->sentence(),
        ];
    }

    public function injury(): static
    {
        return $this->state(fn () => ['severity' => SensitivitySeverity::Injury]);
    }

    public function minor(): static
    {
        return $this->state(fn () => ['severity' => SensitivitySeverity::Minor]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['level' => SensitivityLevel::Blocked]);
    }
}
