<?php

namespace Tests\Feature\Migrations;

use App\Enums\AchievementShelf;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RenameEarlyBirdToItchyFeetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_21_203558_rename_early_bird_to_itchy_feet.php');
    }

    /**
     * The false-start achievement as it was stored before the rename.
     */
    private function falseStart(array $attributes = []): Achievement
    {
        return Achievement::factory()->create(['key' => 'early_bird', 'name' => 'Early Bird', 'shelf' => AchievementShelf::Oof, ...$attributes]);
    }

    public function test_renames_the_row_and_keeps_every_award_and_decision_on_it(): void
    {
        $falseStart = $this->falseStart();
        $reviewer = User::factory()->create();
        $decided = AchievementAward::factory()->for($falseStart)->create(['status' => AwardStatus::Suppressed, 'decided_by' => $reviewer->id, 'decided_at' => now(), 'decision_note' => 'Not funny']);
        $engine = AchievementAward::factory()->for($falseStart)->create();

        $this->migration()->up();

        $itchyFeet = Achievement::firstWhere('key', 'itchy_feet');
        $this->assertSame([$falseStart->id, 'Itchy Feet'], [$itchyFeet->id, $itchyFeet->name]);
        $this->assertDatabaseMissing('achievements', ['key' => 'early_bird']);
        $this->assertSame([$falseStart->id, AwardStatus::Suppressed, 'Not funny'], [$decided->refresh()->achievement_id, $decided->status, $decided->decision_note]);
        $this->assertSame($falseStart->id, $engine->refresh()->achievement_id);
    }

    public function test_keeps_a_name_edited_in_the_cms_and_rewrites_the_supersedes_lists(): void
    {
        $this->falseStart(['name' => 'Jumped the Gun']);
        $other = Achievement::factory()->create(['supersedes' => ['early_bird', 'high_five']]);

        $this->migration()->up();

        $this->assertSame('Jumped the Gun', Achievement::firstWhere('key', 'itchy_feet')->name);
        $this->assertSame(['itchy_feet', 'high_five'], $other->refresh()->supersedes);
    }

    public function test_leaves_an_early_bird_on_the_glory_shelf_alone(): void
    {
        $pole = $this->falseStart(['shelf' => AchievementShelf::Glory]);

        $this->migration()->up();

        $this->assertSame('early_bird', $pole->refresh()->key);
        $this->assertDatabaseMissing('achievements', ['key' => 'itchy_feet']);
    }

    public function test_replaces_an_empty_itchy_feet_row_that_a_sync_created_first(): void
    {
        $falseStart = $this->falseStart();
        Achievement::factory()->create(['key' => 'itchy_feet', 'name' => 'Itchy Feet']);

        $this->migration()->up();

        $this->assertSame([$falseStart->id], Achievement::where('key', 'itchy_feet')->pluck('id')->all());
    }

    public function test_stops_when_an_itchy_feet_row_already_holds_awards(): void
    {
        $falseStart = $this->falseStart();
        AchievementAward::factory()->for(Achievement::factory()->create(['key' => 'itchy_feet']))->create();

        $this->expectException(RuntimeException::class);

        try {
            $this->migration()->up();
        } finally {
            $this->assertSame('early_bird', $falseStart->refresh()->key);
        }
    }

    public function test_rolling_back_puts_the_key_and_name_back(): void
    {
        $falseStart = $this->falseStart();
        $other = Achievement::factory()->create(['supersedes' => ['early_bird']]);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(['early_bird', 'Early Bird'], [$falseStart->refresh()->key, $falseStart->name]);
        $this->assertSame(['early_bird'], $other->refresh()->supersedes);
    }
}
