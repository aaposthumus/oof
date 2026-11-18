<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Nicknames\Breakthroughs;
use App\Achievements\Nicknames\OffSeasonOnly;
use App\Enums\Breakthrough;
use App\Enums\EvolutionStatus;
use App\Enums\NicknameStatus;
use App\Events\NicknameEvolutionProposed;
use App\Events\NicknameEvolved;
use App\Models\Achievement;
use App\Models\AchievementVariant;
use App\Models\Driver;
use App\Models\DriverNickname;
use App\Models\DriverStanding;
use App\Models\NicknameEvolution;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\Season;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DriverNicknameSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class NicknameEvolutionTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private Driver $lindblad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        config(['achievements.events_from' => 2027]);
        Event::fake([NicknameEvolutionProposed::class, NicknameEvolved::class]);

        // Only Lindblad exists, so the seeder gives him LindBud, planned to evolve to LindBloom.
        $this->lindblad = Driver::factory()->create(['jolpica_driver_id' => 'arvid_lindblad', 'given_name' => 'Arvid', 'family_name' => 'Lindblad']);
        $this->seed(DriverNicknameSeeder::class);
    }

    public function test_a_first_podium_for_lindblad_proposes_one_evolution_and_one_event_and_a_rerun_none(): void
    {
        $race = $this->raceWithLindbladIn(position: 3);

        $this->artisan('oof:nicknames', ['--season' => [2027]])->assertSuccessful();
        $this->artisan('oof:nicknames', ['--season' => [2027]])->assertSuccessful();

        $evolution = NicknameEvolution::query()->sole();
        $this->assertSame(['LindBud', 'LindBloom'], [$evolution->from->nickname, $evolution->to->nickname]);
        $this->assertSame(Breakthrough::FirstPodium, $evolution->reason);
        $this->assertSame(EvolutionStatus::Proposed, $evolution->status);
        $this->assertSame('solid_bronze', $evolution->triggerAward->achievement->key);
        $this->assertSame($race->id, $evolution->triggerAward->race_session_id);
        Event::assertDispatchedTimes(NicknameEvolutionProposed::class, 1);
    }

    public function test_a_first_podium_that_is_a_win_is_a_first_win(): void
    {
        $this->raceWithLindbladIn(position: 1);

        $evolution = $this->check()->sole();

        $this->assertSame(Breakthrough::FirstWin, $evolution->reason);
        $this->assertSame('did_the_thing', $evolution->triggerAward->achievement->key);
    }

    public function test_an_earlier_podium_leaves_only_a_first_win(): void
    {
        $this->raceWithLindbladIn(position: 2, round: $this->round(2026, 20));
        $this->raceWithLindbladIn(position: 3, round: $this->round(2027, 3, ['date' => '2027-04-04']));

        $this->assertCount(0, $this->check());

        $this->raceWithLindbladIn(position: 1);

        $this->assertSame(Breakthrough::FirstWin, $this->check()->sole()->reason);
    }

    public function test_nothing_is_proposed_while_the_award_behind_it_is_not_final(): void
    {
        $race = $this->raceWithLindbladIn(position: 3, now: CarbonImmutable::parse('2027-05-05 23:00'));

        $this->assertCount(0, $this->check());

        $this->runEngine($race, CarbonImmutable::parse('2027-06-01'));

        $this->assertCount(1, $this->check());
    }

    public function test_a_top_ten_place_in_the_first_full_season_is_a_strong_first_season(): void
    {
        $last = $this->raceWithLindbladIn(position: 9);
        DriverStanding::create(['round_id' => $last->round_id, 'driver_id' => $this->lindblad->id, 'position' => 8, 'position_text' => '8', 'points' => 64, 'wins' => 0]);

        $this->assertCount(0, $this->check(CarbonImmutable::parse('2027-05-05 23:00')), 'not before the season is final');
        $evolution = $this->check(CarbonImmutable::parse('2027-06-01'))->sole();

        $this->assertSame(Breakthrough::StrongFirstSeason, $evolution->reason);
        $this->assertNull($evolution->trigger_award_id);
    }

    public function test_eleventh_in_the_first_full_season_is_no_breakthrough(): void
    {
        $last = $this->raceWithLindbladIn(position: 9);
        DriverStanding::create(['round_id' => $last->round_id, 'driver_id' => $this->lindblad->id, 'position' => 11, 'position_text' => '11', 'points' => 30, 'wins' => 0]);

        $this->assertCount(0, $this->check(CarbonImmutable::parse('2027-06-01')));
    }

    public function test_a_season_before_the_events_start_proposes_without_an_event(): void
    {
        $this->raceWithLindbladIn(position: 3, round: $this->round(2026, 20), now: CarbonImmutable::parse('2026-06-01'));

        $this->assertCount(1, $this->check(season: 2026));
        Event::assertNotDispatched(NicknameEvolutionProposed::class);
    }

    public function test_an_effective_date_inside_the_season_is_refused_and_an_off_season_one_is_applied_on_its_date(): void
    {
        $this->raceWithLindbladIn(position: 3);
        $evolution = $this->check()->sole();
        $user = User::factory()->create();

        $this->assertRefused(fn () => $evolution->approve($user, CarbonImmutable::parse('2027-12-01')), 'no 2028 calendar yet');

        Round::factory()->for(Season::firstOrCreate(['year' => 2028]))->create(['round_number' => 1, 'date' => '2028-03-05']);

        $this->assertRefused(fn () => $evolution->approve($user, CarbonImmutable::parse('2027-05-01')), 'inside the season');
        $this->assertRefused(fn () => $evolution->approve($user, CarbonImmutable::parse('2028-03-05')), 'on the next season\'s first round');
        $evolution->approve($user, CarbonImmutable::parse('2027-12-15'));
        $this->assertSame(EvolutionStatus::Approved, $evolution->fresh()->status);

        $this->travelTo(CarbonImmutable::parse('2027-12-14 12:00'));
        $this->artisan('oof:nicknames:evolve')->assertSuccessful();
        Event::assertNotDispatched(NicknameEvolved::class);

        $this->travelTo(CarbonImmutable::parse('2027-12-15 00:10'));
        $this->artisan('oof:nicknames:evolve')->assertSuccessful();
        $this->artisan('oof:nicknames:evolve')->assertSuccessful();

        $old = DriverNickname::firstWhere('nickname', 'LindBud');
        $new = DriverNickname::firstWhere('nickname', 'LindBloom');
        $this->assertSame([NicknameStatus::Retired, '2027-12-14'], [$old->status, $old->active_to->toDateString()]);
        $this->assertSame([NicknameStatus::Active, '2027-12-15'], [$new->status, $new->active_from->toDateString()]);
        $this->assertSame(EvolutionStatus::Applied, $evolution->fresh()->status);
        Event::assertDispatchedTimes(NicknameEvolved::class, 1);
        Event::assertDispatched(NicknameEvolved::class, fn (NicknameEvolved $event) => $event->evolution->is($evolution)
            && $event->evolution->effective_on->toDateString() === '2027-12-15'
            && $event->evolution->triggerAward !== null);
    }

    public function test_an_award_keeps_the_nickname_of_its_day_and_follows_a_correction(): void
    {
        AchievementVariant::create(['achievement_id' => Achievement::firstWhere('key', 'solid_bronze')->id, 'driver_id' => $this->lindblad->id, 'name' => 'Bud Bronze']);
        $this->raceWithLindbladIn(position: 3);
        $award = $this->awards('solid_bronze')->sole();
        $this->assertSame('Solid Bronze · LindBud edition', $award->editionSubtitle());

        // The evolution: a new stage from its date; the award keeps the stage it was won under.
        Round::factory()->for(Season::firstOrCreate(['year' => 2028]))->create(['round_number' => 1, 'date' => '2028-03-05']);
        $evolution = $this->check()->sole();
        $evolution->approve(User::factory()->create(), CarbonImmutable::parse('2027-12-15'));
        $evolution->apply();

        $this->assertSame('LindBud', $award->fresh()->nickname()->nickname);
        $this->assertSame('LindBloom', $this->lindblad->fresh()->nicknameOn(CarbonImmutable::parse('2028-01-01'))->nickname);

        // A correction: the same row renamed, shown at once, no new stage and no event.
        DriverNickname::firstWhere('nickname', 'LindBud')->update(['nickname' => 'LindBuddy']);

        $this->assertSame('Solid Bronze · LindBuddy edition', $award->fresh()->editionSubtitle());
        $this->assertSame(2, DriverNickname::query()->where('driver_id', $this->lindblad->id)->count());
        Event::assertDispatchedTimes(NicknameEvolved::class, 1);
    }

    public function test_a_dismissed_evolution_is_never_proposed_again(): void
    {
        $this->raceWithLindbladIn(position: 3);
        $this->check()->sole()->dismiss(User::factory()->create());
        $this->raceWithLindbladIn(position: 1, round: $this->round(2027, 6, ['date' => '2027-05-19']));

        $this->assertCount(0, $this->check());
        $this->assertSame(EvolutionStatus::Dismissed, NicknameEvolution::query()->sole()->status);
    }

    public function test_a_driver_without_a_planned_stage_is_left_alone(): void
    {
        DriverNickname::query()->planned()->delete();
        $this->raceWithLindbladIn(position: 1);

        $this->assertCount(0, $this->check());
    }

    public function test_the_hourly_run_proposes_after_storing_the_awards(): void
    {
        $race = $this->raceWithLindbladIn(position: 3, now: CarbonImmutable::parse('2027-05-05 23:00'));
        $this->travelTo(CarbonImmutable::parse('2027-06-01'));
        $race->update(['achievements_checked_at' => null]);

        $this->artisan('oof:achievements', ['--season' => [2027]])->assertSuccessful();

        $this->assertSame(Breakthrough::FirstPodium, NicknameEvolution::query()->sole()->reason);
    }

    /**
     * A race (by default 2027 round 5, Miami, 5 May) with Lindblad in this
     * position and a car in every place before him, run by the engine.
     */
    private function raceWithLindbladIn(int $position, ?Round $round = null, ?CarbonImmutable $now = null): RaceSession
    {
        $race = $this->raceSession(round: $round ?? Round::query()->whereHas('season', fn ($query) => $query->where('year', 2027))->where('round_number', 5)->first() ?? $this->round(2027, 5));

        foreach (range(1, $position) as $place) {
            $car = $place === $position ? $this->car($race, 6, driver: $this->lindblad) : $this->car($race, 10 + $place);
            $this->resultFor($car, ['position' => $place, 'position_text' => (string) $place, 'grid' => $place, 'classification_order' => $place]);
        }

        $this->runEngine($race, $now ?? CarbonImmutable::parse(($race->round->season->year).'-06-01'));

        return $race;
    }

    /**
     * @return Collection<int, NicknameEvolution>
     */
    private function check(?CarbonImmutable $now = null, int $season = 2027): Collection
    {
        return app(Breakthroughs::class)->check(Season::firstWhere('year', $season), $now ?? CarbonImmutable::parse("{$season}-06-01"));
    }

    private function assertRefused(callable $approve, string $why): void
    {
        try {
            $approve();
            $this->fail("Approved although {$why}.");
        } catch (OffSeasonOnly) {
            $this->assertTrue(true);
        }
    }
}
