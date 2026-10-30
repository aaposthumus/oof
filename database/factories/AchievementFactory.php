<?php

namespace Database\Factories;

use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\SessionType;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2, false),
            'name' => fake()->words(3, true),
            'section' => AchievementSection::Race,
            'shelf' => AchievementShelf::Oof,
            'tier' => AchievementTier::Bronze,
            'points' => AchievementTier::Bronze->points(),
            'session_types' => [SessionType::Race->value],
            'stacks' => false,
            'supersedes' => [],
            'description' => fake()->sentence(),
            'is_enabled' => true,
        ];
    }
}
