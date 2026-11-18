<?php

namespace Database\Factories;

use App\Enums\AwardScope;
use App\Models\AchievementRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AchievementRun>
 */
class AchievementRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => AwardScope::Session,
            'context_key' => 'session:'.fake()->unique()->numberBetween(1, 100_000),
            'season_id' => null,
            'catalogue_version' => sha1(fake()->uuid()),
            'options' => [],
            'stats' => [],
            'is_dry_run' => false,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }
}
