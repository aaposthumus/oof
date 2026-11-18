<?php

namespace Tests\Feature\Achievements;

use App\Achievements\AchievementEngine;
use App\Achievements\OofScoreStandings;
use App\Enums\AchievementShelf;
use App\Enums\AwardScope;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use App\Models\Season;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class SeasonScopeTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private const AFTER_THE_SEASON = '2024-12-31';

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    /**
     * A race of the 2024 season that the engine has run, with a winner in car 99.
     */
    private function race(int $roundNumber, string $date): RaceSession
    {
        $race = $this->raceSession(SessionType::Race, $this->round(2024, $roundNumber, ['date' => $date]));
        $race->update(['achievements_checked_at' => now()]);
        $this->resultFor($this->car($race, 99), ['position' => 1, 'position_text' => '1']);

        return $race;
    }

    private function award(RaceSession $race, DriverEntry $entry, string $key, AwardStatus $status = AwardStatus::Final): AchievementAward
    {
        return AchievementAward::factory()->create([
            'race_session_id' => $race->id,
            'achievement_id' => Achievement::firstWhere('key', $key)->id,
            'driver_entry_id' => $entry->id,
            'driver_id' => $entry->driver_id,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, array<string, int>>|null
     */
    private function runSeason(?string $now = self::AFTER_THE_SEASON): ?array
    {
        $season = Season::firstWhere('year', 2024);
        app(OofScoreStandings::class)->rebuild($season);

        return app(AchievementEngine::class)->runSeason($season, now: CarbonImmutable::parse($now));
    }

    public function test_a_season_is_decided_only_once_its_final_race_is_final(): void
    {
        $first = $this->race(1, '2024-03-02');
        $final = $this->race(2, '2024-03-09');
        $this->message($first, 'SAFETY CAR DEPLOYED', ['lap_number' => 3]);

        $this->assertNull($this->runSeason('2024-03-09 23:30:00'));
        $this->assertCount(0, $this->awards());

        $final->update(['achievements_checked_at' => null]);
        $this->assertNull($this->runSeason());

        $final->update(['achievements_checked_at' => now()]);
        $this->assertSame(1, $this->runSeason()['frequent_flyer']['created']);
    }

    public function test_frequent_flyer_counts_the_seasons_deployments_in_races_and_sprints(): void
    {
        $first = $this->race(1, '2024-03-02');
        $sprint = $this->raceSession(SessionType::Sprint, $first->round);
        $final = $this->race(2, '2024-03-09');
        $this->message($first, 'SAFETY CAR DEPLOYED', ['lap_number' => 3]);
        $this->message($first, 'VIRTUAL SAFETY CAR DEPLOYED', ['lap_number' => 9]);
        $this->message($sprint, 'SAFETY CAR DEPLOYED', ['lap_number' => 1]);
        $this->message($final, 'SAFETY CAR DEPLOYED', ['lap_number' => 30]);

        $this->runSeason();

        $award = $this->awards('frequent_flyer')->sole();
        $this->assertSame('3 deployments', $award->detail);
        $this->assertSame(AwardScope::Season, $award->scope);
        $this->assertSame("season:{$final->round->season_id}", $award->context_key);
        $this->assertNull($award->race_session_id);
        $this->assertNull($award->round_id);
        $this->assertNull($award->driver_id);
        $this->assertSame('2024-03-09', $award->occurred_on->toDateString());
        $this->assertSame(AwardStatus::Final, $award->status);
        $this->assertEquals([['session' => 'Round 1 Sprint', 'count' => 1], ['session' => 'Round 1 Race', 'count' => 1], ['session' => 'Round 2 Race', 'count' => 1]], $award->evidence['rows']);

        $this->assertSame([], array_filter($this->runSeason()['frequent_flyer'], fn (int $count, string $label) => $label !== 'found' && $count > 0, ARRAY_FILTER_USE_BOTH));
    }

    public function test_most_collected_goes_to_the_driver_with_most_final_awards_of_one_achievement_and_a_tie_is_shared(): void
    {
        $first = $this->race(1, '2024-03-02');
        $final = $this->race(2, '2024-03-09');
        [$alice, $bob, $carol] = collect([1, 2, 3])->map(fn (int $number) => $this->car($final, $number))->all();

        foreach ([$alice, $alice, $bob, $bob, $carol] as $index => $entry) {
            $this->award($index % 2 === 0 ? $first : $final, $entry, 'lapped_dog');
        }

        $this->award($first, $carol, 'lapped_dog', AwardStatus::Pending);
        $this->award($first, $carol, 'outside_the_lines');
        $this->award($first, $alice, 'did_the_thing');
        $this->award($final, $alice, 'did_the_thing');

        $this->runSeason();

        $collected = $this->awards('most_collected');
        $this->assertEqualsCanonicalizing([$alice->driver_id, $bob->driver_id], $collected->pluck('driver_id')->all());
        $this->assertSame(['Lapped Dog ×2'], $collected->pluck('detail')->unique()->values()->all());
        // On the car of the driver's last race, and worth nothing: the Oofies shelf has no points.
        $this->assertSame($alice->id, $collected->firstWhere('driver_id', $alice->driver_id)->driver_entry_id);
        $this->assertSame(AchievementShelf::Oofies, $collected->first()->shelf);
        $this->assertSame(0, $collected->first()->points);
    }

    public function test_most_collected_leaves_nothing_to_see_here_out(): void
    {
        $first = $this->race(1, '2024-03-02');
        $final = $this->race(2, '2024-03-09');
        $alice = $this->car($final, 1);
        $this->award($first, $alice, 'nothing_to_see_here');
        $this->award($final, $alice, 'nothing_to_see_here');

        $this->runSeason();

        $this->assertCount(0, $this->awards('most_collected'));
    }

    public function test_the_oofscore_champion_tops_the_final_standings(): void
    {
        $first = $this->race(1, '2024-03-02');
        $final = $this->race(2, '2024-03-09');
        $alice = $this->car($final, 1);
        $bob = $this->car($final, 2);
        $this->award($first, $alice, 'bumper_cars');        // silver, 25, ×1
        $this->award($final, $bob, 'outside_the_lines');    // bronze, 10, ×1

        $this->runSeason();

        $champion = $this->awards('oofscore_champion')->sole();
        $this->assertSame($alice->driver_id, $champion->driver_id);
        $this->assertSame('25 points', $champion->detail);
    }

    public function test_the_rarest_award_goes_to_every_holder_and_a_tie_to_the_higher_tier(): void
    {
        $final = $this->race(1, '2024-03-09');
        [$alice, $bob, $carol] = collect([1, 2, 3])->map(fn (int $number) => $this->car($final, $number))->all();
        $this->award($final, $alice, 'lapped_dog');
        $this->award($final, $bob, 'lapped_dog');
        $this->award($final, $alice, 'forgot_the_keys');   // gold, once
        $this->award($final, $carol, 'good_boy');          // silver, once

        $this->runSeason();

        $rarest = $this->awards('rarest_award')->sole();
        $this->assertSame($alice->driver_id, $rarest->driver_id);
        $this->assertSame('Forgot the Keys', $rarest->detail);
    }

    public function test_the_season_scope_is_never_looked_for_in_a_session(): void
    {
        $race = $this->race(1, '2024-03-09');
        $this->message($race, 'SAFETY CAR DEPLOYED', ['lap_number' => 3]);
        $this->resultFor($this->car($race, 1, null, Driver::factory()->create()), ['position' => 1, 'position_text' => '1']);

        $this->runEngine($race);

        $this->assertCount(0, $this->awards('frequent_flyer'));
    }
}
