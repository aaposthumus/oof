<?php

namespace Tests\Feature\Achievements;

use App\Achievements\OofScoreStandings;
use App\Achievements\SeasonNotClosable;
use App\Enums\AchievementTier;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Events\OofScoreStandingsUpdated;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\Driver;
use App\Models\OofscoreStanding;
use App\Models\RaceSession;
use App\Models\Round;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class OofScoreStandingsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    private function race(int $roundNumber, int $year = 2024): RaceSession
    {
        $race = $this->raceSession(SessionType::Race, $this->round($year, $roundNumber, ['date' => "{$year}-0".min(9, $roundNumber).'-01']));
        $race->update(['achievements_checked_at' => now()]);
        $this->resultFor($this->car($race, 99), ['position' => 1, 'position_text' => '1']);

        return $race;
    }

    private function award(RaceSession $race, Driver $driver, string $key, AwardStatus $status = AwardStatus::Final): AchievementAward
    {
        return AchievementAward::factory()->create([
            'race_session_id' => $race->id,
            'achievement_id' => Achievement::firstWhere('key', $key)->id,
            'driver_id' => $driver->id,
            'status' => $status,
        ]);
    }

    /**
     * @return array<int, array{int, int, int, int|null}> Driver ID => [points, awards, position, change]
     */
    private function after(RaceSession $race): array
    {
        return OofscoreStanding::query()->where('round_id', $race->round_id)->get()
            ->mapWithKeys(fn (OofscoreStanding $row) => [$row->driver_id => [$row->points, $row->awards_count, $row->position, $row->position_change]])
            ->sortKeys()
            ->all();
    }

    public function test_sums_final_oof_awards_round_by_round_and_leaves_out_the_rest(): void
    {
        [$alice, $bob, $carol] = Driver::factory()->count(3)->create();
        $first = $this->race(1);
        $second = $this->race(2);
        $this->award($first, $alice, 'lapped_dog');               // bronze, 10
        $this->award($first, $bob, 'good_boy');                   // silver, 25
        $this->award($first, $carol, 'lapped_dog', AwardStatus::Pending);
        $this->award($first, $carol, 'did_the_thing');            // glory: no points
        $this->award($second, $alice, 'forgot_the_keys');         // gold, 50
        $this->award($second, $bob, 'outside_the_lines', AwardStatus::Suppressed);
        $this->award($second, $carol, 'nothing_to_see_here');     // 0 points, but an award

        app(OofScoreStandings::class)->rebuild($first->round->season);

        $this->assertSame([$alice->id => [10, 1, 2, null], $bob->id => [25, 1, 1, null]], $this->after($first));
        $this->assertSame([$alice->id => [60, 2, 1, 1], $bob->id => [25, 1, 2, -1], $carol->id => [0, 1, 3, null]], $this->after($second));
        $this->assertSame([$alice->id, $bob->id, $carol->id], array_keys($this->after($second)), 'Drivers in ID order, for the comparison.');
    }

    public function test_equal_points_share_a_place(): void
    {
        [$alice, $bob, $carol] = Driver::factory()->count(3)->create();
        $race = $this->race(1);
        $this->award($race, $alice, 'good_boy');
        $this->award($race, $bob, 'good_boy');
        $this->award($race, $carol, 'lapped_dog');

        app(OofScoreStandings::class)->rebuild($race->round->season);

        $this->assertSame([1, 1, 3], array_column(array_values($this->after($race)), 2));
    }

    public function test_a_rebuild_from_scratch_gives_the_same_standings_and_changes_nothing(): void
    {
        [$alice, $bob] = Driver::factory()->count(2)->create();
        $race = $this->race(1);
        $this->award($race, $alice, 'good_boy');
        $this->award($race, $bob, 'lapped_dog');
        $standings = app(OofScoreStandings::class);

        $standings->rebuild($race->round->season);
        $before = $this->after($race);
        OofscoreStanding::query()->delete();
        $standings->rebuild($race->round->season);

        $this->assertSame($before, $this->after($race));
        $this->assertSame(0, $standings->rebuild($race->round->season)['changed_rounds']);
    }

    public function test_a_round_without_awards_adds_no_row(): void
    {
        $alice = Driver::factory()->create();
        $first = $this->race(1);
        $empty = $this->race(2);
        $this->award($first, $alice, 'lapped_dog');

        app(OofScoreStandings::class)->rebuild($first->round->season);

        $this->assertSame([], $this->after($empty));
    }

    public function test_closing_a_season_freezes_its_standings_and_points(): void
    {
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $award = $this->award($race, $alice, 'lapped_dog');
        $standings = app(OofScoreStandings::class);

        $standings->close($race->round->season, CarbonImmutable::parse('2024-12-31'));

        $this->assertTrue($race->round->season->fresh()->isClosed());
        $this->assertSame([$alice->id => [10, 1, 1, null]], $this->after($race));

        $this->award($race, $alice, 'good_boy');
        $award->achievement->update(['tier' => AchievementTier::Platinum]);

        $this->assertNull($standings->rebuild($race->round->season->fresh()));
        $this->assertSame([$alice->id => [10, 1, 1, null]], $this->after($race));
        $this->assertSame(10, $award->fresh()->points);
    }

    public function test_a_season_cannot_be_closed_before_its_last_race_is_final_or_while_awards_wait(): void
    {
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $pending = $this->award($race, $alice, 'lapped_dog', AwardStatus::Pending);
        $standings = app(OofScoreStandings::class);

        $this->assertSame(['its last race is not final yet', '1 awards still wait for review'], $standings->reasonsNotToClose($race->round->season, CarbonImmutable::parse('2024-01-02')));

        $pending->update(['status' => AwardStatus::Final]);
        $race->update(['achievements_checked_at' => null]);
        $this->assertSame(['its last race has not been run by the achievements engine'], $standings->reasonsNotToClose($race->round->season, CarbonImmutable::parse('2024-12-31')));

        $race->update(['achievements_checked_at' => now()]);
        $standings->close($race->round->season, CarbonImmutable::parse('2024-12-31'));

        $this->expectException(SeasonNotClosable::class);
        $standings->close($race->round->season->fresh(), CarbonImmutable::parse('2024-12-31'));
    }

    public function test_the_standings_event_is_dispatched_only_for_live_seasons_and_only_on_a_change(): void
    {
        Event::fake([OofScoreStandingsUpdated::class]);
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $this->award($race, $alice, 'lapped_dog');
        $standings = app(OofScoreStandings::class);

        config(['achievements.events_from' => 2025]);
        $standings->rebuild($race->round->season);
        Event::assertNotDispatched(OofScoreStandingsUpdated::class);

        config(['achievements.events_from' => 2024]);
        OofscoreStanding::query()->delete();
        $standings->rebuild($race->round->season);
        Event::assertDispatched(OofScoreStandingsUpdated::class, fn (OofScoreStandingsUpdated $event) => $event->seasonId === $race->round->season_id && $event->changedRoundIds === [$race->round_id]);

        $standings->rebuild($race->round->season);
        Event::assertDispatchedTimes(OofScoreStandingsUpdated::class, 1);
    }

    public function test_the_engine_run_rebuilds_the_seasons_it_touched(): void
    {
        config(['achievements.first_season' => 2024]);
        $race = $this->raceSession();
        $car = $this->car($race, 44);
        $this->resultFor($car, ['position' => 7, 'position_text' => '7', 'grid' => 7]);
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');
        $this->travelTo(CarbonImmutable::parse('2024-06-01'));

        $this->artisan('oof:achievements', ['--season' => [2024]])->assertSuccessful();

        $counted = AchievementAward::query()->where('driver_id', $car->driver_id)->where('status', AwardStatus::Final)->where('shelf', 'oof')->get();
        $this->assertContains('smile_for_the_camera', $counted->map(fn (AchievementAward $award) => $award->achievement->key));
        $this->assertSame([$car->driver_id => [$counted->sum('points'), $counted->count(), 1, null]], $this->after($race));
    }

    public function test_the_commands_rebuild_and_close(): void
    {
        config(['achievements.first_season' => 2024]);
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $this->award($race, $alice, 'lapped_dog');
        $this->travelTo(CarbonImmutable::parse('2024-12-31'));

        $this->artisan('oof:oofscore', ['--season' => [2024]])->expectsOutputToContain('rebuilt')->assertSuccessful();
        $this->artisan('oof:oofscore:close', ['season' => 2024, '--force' => true])->expectsOutputToContain('2024 is closed.')->assertSuccessful();
        $this->artisan('oof:oofscore', ['--season' => [2024]])->expectsOutputToContain('closed, frozen')->assertSuccessful();
        $this->artisan('oof:oofscore:close', ['season' => 2024, '--force' => true])->expectsOutputToContain('it is closed already')->assertFailed();
        $this->assertInstanceOf(Round::class, $race->round);
    }
}
