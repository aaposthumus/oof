<?php

namespace Tests\Feature\Migrations;

use App\Achievements\CatalogueSync;
use App\Enums\AchievementShelf;
use App\Models\Achievement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ApplyPhase12cToAchievementsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_21_213411_apply_phase_12c_to_achievements.php');
    }

    public function test_plot_twist_and_clean_sweep_gain_their_supersedes_and_the_podium_says_grand_prix(): void
    {
        $plotTwist = Achievement::factory()->create(['key' => 'plot_twist', 'supersedes' => []]);
        $cleanSweep = Achievement::factory()->create(['key' => 'clean_sweep', 'supersedes' => ['did_the_thing']]);
        $win = Achievement::factory()->create(['key' => 'did_the_thing', 'description' => 'Win.']);
        $second = Achievement::factory()->create(['key' => 'silver_lining', 'description' => 'Runner-up, edited in the CMS.']);

        $this->migration()->up();

        $this->assertSame(['early_bird'], $plotTwist->fresh()->supersedes);
        $this->assertSame(['did_the_thing', 'purple_patch'], $cleanSweep->fresh()->supersedes);
        $this->assertSame('Grand Prix win.', $win->fresh()->description);
        $this->assertSame('Runner-up, edited in the CMS.', $second->fresh()->description);

        $this->migration()->up();
        $this->assertSame(['did_the_thing', 'purple_patch'], $cleanSweep->fresh()->supersedes);
    }

    public function test_rolling_back_takes_them_out_again(): void
    {
        $cleanSweep = Achievement::factory()->create(['key' => 'clean_sweep', 'supersedes' => ['did_the_thing']]);
        $win = Achievement::factory()->create(['key' => 'did_the_thing', 'description' => 'Win.']);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(['did_the_thing'], $cleanSweep->fresh()->supersedes);
        $this->assertSame('Win.', $win->fresh()->description);
    }

    public function test_it_refuses_to_run_while_the_false_start_is_still_called_early_bird(): void
    {
        Achievement::factory()->create(['key' => 'early_bird', 'shelf' => AchievementShelf::Oof]);

        $this->expectException(RuntimeException::class);

        $this->migration()->up();
    }

    public function test_the_sync_refuses_to_turn_the_old_early_bird_into_the_pole(): void
    {
        $falseStart = Achievement::factory()->create(['key' => 'early_bird', 'shelf' => AchievementShelf::Oof, 'session_types' => ['race']]);

        try {
            app(CatalogueSync::class)->apply();
            $this->fail('The sync should refuse.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('itchy_feet', $exception->getMessage());
        }

        $this->assertSame(['race'], $falseStart->fresh()->session_types);
    }
}
