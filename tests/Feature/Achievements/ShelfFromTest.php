<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Catalogue;
use App\Achievements\Facts\Requirements;
use App\Achievements\OofScoreStandings;
use App\Achievements\SeasonNotClosable;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\SessionType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\OofscoreStanding;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * OOF counts from 2023 (Phase 11b, decision D2): earlier OOF and Safety Car
 * awards score 0 and stay out of the OOFscore, the Oofies, Close season,
 * the stamp books and automatic publishing; glory counts from 1950.
 */
class ShelfFromTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private function award(Achievement $achievement, int $year): AchievementAward
    {
        $race = $this->raceSession(SessionType::Race, $this->round($year));

        return AchievementAward::factory()->create([
            'achievement_id' => $achievement->id,
            'race_session_id' => $race->id,
            'driver_entry_id' => $this->car($race, 1)->id,
        ]);
    }

    public function test_an_oof_award_before_2023_scores_0(): void
    {
        $gold = Achievement::factory()->create(['tier' => AchievementTier::Gold]);

        $this->assertSame(0, $this->award($gold, 1990)->points);
        $this->assertSame(50, $this->award($gold, 2024)->points);
    }

    public function test_a_tier_change_keeps_the_earlier_awards_at_0(): void
    {
        $bronze = Achievement::factory()->create(['tier' => AchievementTier::Bronze]);
        $historic = $this->award($bronze, 1990);
        $live = $this->award($bronze, 2024);

        $bronze->update(['tier' => AchievementTier::Platinum]);
        $bronze->updateOpenSeasonAwards();

        $this->assertSame([AchievementTier::Platinum, 0], [$historic->fresh()->tier, $historic->fresh()->points]);
        $this->assertSame([AchievementTier::Platinum, 100], [$live->fresh()->tier, $live->fresh()->points]);
    }

    public function test_no_oofscore_and_no_close_season_before_2023(): void
    {
        $this->award(Achievement::factory()->create(), 1990);
        $season = Season::firstWhere('year', 1990);

        $this->assertNull(app(OofScoreStandings::class)->rebuild($season));
        $this->assertSame(0, OofscoreStanding::query()->where('season_id', $season->id)->count());
        $this->assertContains('the OOFscore counts from 2023', app(OofScoreStandings::class)->reasonsNotToClose($season, now()->toImmutable()));

        $this->expectException(SeasonNotClosable::class);
        app(OofScoreStandings::class)->close($season);
    }

    public function test_the_oofies_are_decided_from_2023(): void
    {
        $this->catalogue();
        $requirements = app(Requirements::class);

        $this->assertFalse($requirements->allowsSeason('most_collected', 1990));
        $this->assertFalse($requirements->allowsSeason('oofscore_champion', 2022));
        $this->assertTrue($requirements->allowsSeason('rarest_award', 2023));
    }

    public function test_stamp_books_and_automatic_publishing_leave_out_oof_before_2023_but_keep_glory(): void
    {
        $oof = Achievement::factory()->create();
        $glory = Achievement::factory()->create(['shelf' => AchievementShelf::Glory, 'tier' => AchievementTier::Gold]);
        $historicOof = $this->award($oof, 1990);
        $liveOof = $this->award($oof, 2024);
        $historicGlory = $this->award($glory, 1955);

        $counting = AchievementAward::query()->countingOnTheirShelf()->pluck('id')->sort()->values()->all();
        $this->assertSame([$liveOof->id, $historicGlory->id], $counting);
        $this->assertNotContains($historicOof->id, AchievementAward::query()->publishable()->pluck('id')->all());
        $this->assertContains($historicGlory->id, AchievementAward::query()->publishable()->pluck('id')->all());
    }

    public function test_the_curated_clean_sweeps_are_part_of_the_catalogue_version(): void
    {
        $this->catalogue();
        $version = app(Catalogue::class)->version();

        config(['curated_clean_sweeps.races' => [['season' => 1963, 'round' => 3, 'driver' => 'clark']]]);

        $this->assertNotSame($version, app(Catalogue::class)->version());
    }

    public function test_the_season_settings_are_not_part_of_the_catalogue_version(): void
    {
        $this->catalogue();
        $version = app(Catalogue::class)->version();

        config(['achievements.first_season' => 1960, 'achievements.live_from' => 2024, 'achievements.shelf_from.oof' => 2024]);

        $this->assertSame($version, app(Catalogue::class)->version());
    }
}
