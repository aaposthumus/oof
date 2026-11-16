<?php

namespace Tests\Feature\Migrations;

use App\Enums\AchievementTier;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AlignAchievementsWithTheConceptTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_16_194131_align_achievements_with_the_concept.php');
    }

    /**
     * An achievement as the catalogue held it before the migration.
     */
    private function old(string $key, array $attributes = []): Achievement
    {
        return Achievement::factory()->create(['key' => $key, ...$attributes]);
    }

    public function test_renames_the_keys_and_every_list_that_names_them(): void
    {
        $this->old('colouring_outside_the_lines', ['name' => 'Colouring Outside the Lines']);
        $this->old('note_sent_home_to_parents', ['supersedes' => ['colouring_outside_the_lines']]);
        $this->old('high_five', ['supersedes' => ['it_was_like_that_when_i_got_there', 'colouring_outside_the_lines', 'note_sent_home_to_parents']]);

        $this->migration()->up();

        $this->assertSame('Outside the Lines', Achievement::firstWhere('key', 'outside_the_lines')->name);
        $this->assertSame(['outside_the_lines'], Achievement::firstWhere('key', 'teachers_stare')->supersedes);
        $this->assertSame(['it_was_like_that_when_i_got_there', 'outside_the_lines', 'teachers_stare'], Achievement::firstWhere('key', 'high_five')->supersedes);
        $this->assertDatabaseMissing('achievements', ['key' => 'colouring_outside_the_lines']);
    }

    public function test_splits_the_podium_by_place_and_keeps_a_persons_decision(): void
    {
        $podium = $this->old('standing_room_only', ['tier' => AchievementTier::Bronze]);
        $this->old('did_the_thing', ['tier' => AchievementTier::Bronze, 'supersedes' => ['standing_room_only']]);
        $this->old('lottery_ticket', ['supersedes' => ['did_the_thing', 'standing_room_only']]);
        $reviewer = User::factory()->create();
        $second = AchievementAward::factory()->for($podium)->create(['detail' => 'P2', 'status' => AwardStatus::Suppressed, 'decided_by' => $reviewer->id, 'decided_at' => now(), 'decision_note' => 'Not funny']);
        $third = AchievementAward::factory()->for($podium)->create(['detail' => 'P3']);

        $this->migration()->up();

        $silver = Achievement::firstWhere('key', 'silver_lining');
        $bronze = Achievement::firstWhere('key', 'solid_bronze');
        $this->assertSame([AchievementTier::Silver, AchievementTier::Bronze], [$silver->tier, $bronze->tier]);
        $this->assertSame([$silver->id, null, AwardStatus::Suppressed, 'Not funny'], [$second->refresh()->achievement_id, $second->detail, $second->status, $second->decision_note]);
        $this->assertSame([$bronze->id, null], [$third->refresh()->achievement_id, $third->detail]);
        $this->assertSame(AchievementTier::Gold, Achievement::firstWhere('key', 'did_the_thing')->tier);
        $this->assertSame([], Achievement::firstWhere('key', 'did_the_thing')->supersedes);
        $this->assertSame(['did_the_thing'], Achievement::firstWhere('key', 'giant_slayer')->supersedes);
    }

    public function test_replaces_a_seeded_description_but_keeps_one_edited_in_the_cms(): void
    {
        $this->old('by_a_whisker', ['description' => 'Knocked out by less than 0.05 s.']);
        $this->old('holding_the_door', ['description' => 'Slowest of them all in Q1.']);

        $this->migration()->up();

        $this->assertSame('Knocked out by less than 0.015 seconds.', Achievement::firstWhere('key', 'by_a_whisker')->description);
        $this->assertSame('Slowest of them all in Q1.', Achievement::firstWhere('key', 'holding_the_door')->description);
    }

    public function test_rolling_back_puts_the_podium_and_the_keys_back(): void
    {
        $podium = $this->old('standing_room_only', ['name' => 'Standing Room Only', 'tier' => AchievementTier::Bronze]);
        $this->old('did_the_thing', ['tier' => AchievementTier::Bronze, 'supersedes' => ['standing_room_only']]);
        $this->old('note_sent_home_to_parents', ['supersedes' => ['colouring_outside_the_lines']]);
        $second = AchievementAward::factory()->for($podium)->create(['detail' => 'P2']);
        $third = AchievementAward::factory()->for($podium)->create(['detail' => 'P3']);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(['Standing Room Only', AchievementTier::Bronze], [$podium->refresh()->name, $podium->tier]);
        $this->assertSame([$podium->id, 'P2'], [$second->refresh()->achievement_id, $second->detail]);
        $this->assertSame([$podium->id, 'P3'], [$third->refresh()->achievement_id, $third->detail]);
        $this->assertSame(['standing_room_only'], Achievement::firstWhere('key', 'did_the_thing')->supersedes);
        $this->assertSame(['colouring_outside_the_lines'], Achievement::firstWhere('key', 'note_sent_home_to_parents')->supersedes);
        $this->assertDatabaseMissing('achievements', ['key' => 'solid_bronze']);
    }
}
