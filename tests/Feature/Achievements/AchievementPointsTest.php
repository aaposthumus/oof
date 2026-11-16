<?php

namespace Tests\Feature\Achievements;

use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Models\Achievement;
use Tests\TestCase;

class AchievementPointsTest extends TestCase
{
    public function test_an_oof_achievement_earns_the_points_configured_for_its_tier(): void
    {
        config(['achievements.points.tiers.gold' => 60]);

        $this->assertSame(60, Achievement::factory()->make(['tier' => AchievementTier::Gold])->points());
        $this->assertSame(10, Achievement::factory()->make(['tier' => AchievementTier::Bronze])->points());
    }

    public function test_an_override_replaces_the_tiers_points(): void
    {
        config(['achievements.points.overrides.lapped_dog' => 5]);

        $this->assertSame(5, Achievement::factory()->make(['key' => 'lapped_dog', 'tier' => AchievementTier::Bronze])->points());
        $this->assertSame(0, Achievement::factory()->make(['key' => 'nothing_to_see_here', 'tier' => AchievementTier::Bronze])->points());
    }

    public function test_only_scoring_shelves_and_achievements_with_a_tier_earn_points(): void
    {
        $this->assertSame(0, Achievement::factory()->make(['shelf' => AchievementShelf::Glory, 'tier' => AchievementTier::Platinum])->points());
        $this->assertSame(0, Achievement::factory()->make(['shelf' => AchievementShelf::SafetyCar, 'tier' => null])->points());
        $this->assertSame(0, Achievement::factory()->make(['tier' => null])->points());
    }
}
