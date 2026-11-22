<?php

namespace Tests\Feature\Migrations;

use App\Enums\AchievementShelf;
use App\Models\Achievement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApplyThePenaltyTierRuleToOffenceAchievementsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ALL = ['it_was_like_that_when_i_got_there', 'sibling_rivalry', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'];

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_22_185559_apply_the_penalty_tier_rule_to_offence_achievements.php');
    }

    private function row(string $key): Achievement
    {
        return Achievement::factory()->create(['key' => $key, 'shelf' => AchievementShelf::Oof, 'supersedes' => self::ALL]);
    }

    public function test_silver_offences_keep_only_high_five_and_gold_ones_keep_perfect_ten_too(): void
    {
        $silver = $this->row('bumper_cars');
        $gold = $this->row('shortcut_tax');
        $platinum = $this->row('line_dancing');

        $this->migration()->up();

        $this->assertSame(['it_was_like_that_when_i_got_there', 'sibling_rivalry', 'high_five'], $silver->fresh()->supersedes);
        $this->assertSame(['it_was_like_that_when_i_got_there', 'sibling_rivalry', 'high_five', 'perfect_ten'], $gold->fresh()->supersedes);
        $this->assertSame(self::ALL, $platinum->fresh()->supersedes);

        $this->migration()->up();
        $this->assertSame(['it_was_like_that_when_i_got_there', 'sibling_rivalry', 'high_five'], $silver->fresh()->supersedes);
    }

    public function test_rolling_back_restores_them_and_missing_rows_are_fine(): void
    {
        $silver = $this->row('smile_for_the_camera');

        $this->migration()->up();
        $this->migration()->down();

        $this->assertEqualsCanonicalizing(self::ALL, $silver->fresh()->supersedes);
    }
}
