<?php

namespace Database\Factories;

use App\Enums\AwardConfidence;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\RaceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AchievementAward>
 */
class AchievementAwardFactory extends Factory
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
            'achievement_id' => Achievement::factory(),
            'instance_key' => fake()->unique()->bothify('session:####'),
            'event_key' => 'session',
            'evidence' => ['source' => 'race_control', 'messages' => []],
            'confidence' => AwardConfidence::High,
            'status' => AwardStatus::Final,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => AwardStatus::Pending]);
    }
}
