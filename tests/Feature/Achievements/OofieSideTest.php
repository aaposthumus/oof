<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Catalogue;
use App\Achievements\CatalogueSync;
use App\Achievements\Facts\Requirements;
use App\Enums\AchievementShelf;
use App\Enums\AchievementSide;
use App\Enums\AwardScope;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The side of an Oofie (Phase 12b): glory or oof. A glory-side Oofie counts
 * like the glory shelf, from 1950; an OOF-side one like the Oofies, from
 * 2023. The side is a mechanic, written by the sync.
 */
class OofieSideTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    /**
     * A season award that is only catalogue data: the most Grand Prix wins.
     */
    private function addMostWins(string $key, string $side): void
    {
        config(["achievement_catalogue.achievements.{$key}" => [
            ...config('achievement_catalogue.achievements.most_collected'),
            'name' => str($key)->headline()->toString(),
            'side' => $side,
            'sessions' => ['race'],
            'trigger' => ['type' => 'aggregate', 'source' => 'facts', 'fact' => 'result', 'where' => [['position', '=', 1]], 'group' => 'driver', 'measure' => 'count', 'pick' => 'max'],
        ]]);
        app(CatalogueSync::class)->apply();
    }

    private function seasonAward(Achievement $achievement, int $year): AchievementAward
    {
        $season = Season::firstOrCreate(['year' => $year]);
        $entry = $this->car($this->raceSession(round: $this->round($year, AchievementAward::query()->count() + 1)), 1);

        return AchievementAward::factory()->create([
            'achievement_id' => $achievement->id,
            'scope' => AwardScope::Season,
            'context_key' => "season:{$season->id}",
            'season_id' => $season->id,
            'race_session_id' => null,
            'driver_entry_id' => $entry->id,
            'driver_id' => $entry->driver_id,
            'status' => AwardStatus::Final,
        ]);
    }

    public function test_the_sync_writes_the_side_and_the_oofies_so_far_are_on_the_oof_side(): void
    {
        foreach (['most_collected', 'oofscore_champion', 'rarest_award'] as $key) {
            $this->assertSame(AchievementSide::Oof, Achievement::firstWhere('key', $key)->side);
        }

        Achievement::where('key', 'rarest_award')->update(['side' => 'glory']);
        app(CatalogueSync::class)->apply();

        $this->assertSame(AchievementSide::Oof, Achievement::firstWhere('key', 'rarest_award')->side);
        $this->assertNull(Achievement::firstWhere('key', 'did_the_thing')->side);
    }

    public function test_every_oofie_in_the_catalogue_names_its_side_and_nothing_else_has_one(): void
    {
        foreach (app(Catalogue::class)->entries() as $key => $entry) {
            $this->assertSame($entry['shelf'] === AchievementShelf::Oofies->value, isset($entry['side']), "[{$key}]");
        }
    }

    public function test_a_glory_side_oofie_counts_from_1950_and_an_oof_side_one_from_2023(): void
    {
        $this->addMostWins('most_wins_glory', 'glory');
        $this->addMostWins('most_wins_oof', 'oof');
        $glory = Achievement::firstWhere('key', 'most_wins_glory');
        $oof = Achievement::firstWhere('key', 'most_wins_oof');

        $this->assertSame([1950, 2023], [$glory->firstSeason(), $oof->firstSeason()]);

        $counting = [$this->seasonAward($glory, 1990), $this->seasonAward($glory, 2024), $this->seasonAward($oof, 2024)];
        $this->seasonAward($oof, 1990);

        $this->assertEqualsCanonicalizing(
            collect($counting)->pluck('id')->all(),
            AchievementAward::query()->where('shelf', AchievementShelf::Oofies)->countingOnTheirShelf()->pluck('id')->all(),
        );
    }

    public function test_an_oofie_is_decided_from_the_season_its_side_counts_whatever_its_data_allows(): void
    {
        $this->addMostWins('most_wins_glory', 'glory');
        $this->addMostWins('most_wins_oof', 'oof');
        $requirements = app(Requirements::class);

        $this->assertSame(1950, $requirements->for('most_wins_glory')['available_from']);
        $this->assertSame(2023, $requirements->for('most_wins_oof')['available_from']);
        $this->assertTrue($requirements->allowsSeason('most_wins_glory', 1990));
        $this->assertFalse($requirements->allowsSeason('most_wins_oof', 1990));
        $this->assertSame(2023, Achievement::firstWhere('key', 'most_wins_oof')->available_from);
    }
}
