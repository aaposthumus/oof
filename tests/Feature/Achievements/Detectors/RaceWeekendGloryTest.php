<?php

namespace Tests\Feature\Achievements\Detectors;

use App\Enums\AwardStatus;
use App\Enums\RecipientType;
use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\Circuit;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\LapTime;
use App\Models\Position;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\SeasonTeam;
use App\Models\SessionLap;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The race-weekend glory of Phase 12c: per achievement a hit, a miss, and
 * what it replaces or stands beside once the rules have run.
 */
class RaceWeekendGloryTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finish(RaceSession $session, int $number, int $position, array $attributes = [], ?SeasonTeam $team = null, ?Driver $driver = null): DriverEntry
    {
        $entry = $this->car($session, $number, $team, $driver);
        $this->resultFor($entry, ['position' => $position, 'position_text' => (string) $position, 'classification_order' => $position, 'grid' => $position, 'status' => 'Finished', 'laps_completed' => 3, ...$attributes]);

        return $entry;
    }

    /**
     * @return list<string>
     */
    private function storedKeys(): array
    {
        return $this->awards()->map(fn (AchievementAward $award) => $award->achievement->key)->unique()->sort()->values()->all();
    }

    /**
     * An earlier race in the team-form window in which the given team is
     * fifth, outside the top four, so its win or pole is a surprise.
     */
    private function earlierRace(Round $round, SeasonTeam $team): void
    {
        $earlier = $this->raceSession(SessionType::Race, $this->round($round->season->year, $round->round_number - 1));

        foreach ([25, 18, 15, 12] as $index => $points) {
            $this->finish($earlier, 90 + $index, $index + 1, ['points' => $points]);
        }

        $this->finish($earlier, 99, 5, ['points' => 0], $team);
    }

    // Early Bird

    public function test_early_bird_is_the_grand_prix_pole(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $pole = $this->car($qualifying, 1);
        $this->qualifying($pole, 1, 90_000, 89_000, 88_000);
        $this->qualifying($this->car($qualifying, 2), 2, 90_100, 89_100, 88_100);

        $this->assertSame([$pole->id], $this->detect('early_bird', $qualifying)->pluck('recipient.entryId')->all());
    }

    public function test_early_bird_comes_from_the_grid_only_without_qualifying_data(): void
    {
        $race = $this->raceSession(SessionType::Race, $this->round(1975));
        $pole = $this->finish($race, 1, 2, ['grid' => 1]);
        $this->finish($race, 2, 1, ['grid' => 2]);
        $this->assertSame([$pole->id], $this->detect('early_bird', $race)->pluck('recipient.entryId')->all());

        $modern = $this->raceSession();
        $this->finish($modern, 1, 1);
        $qualifying = $this->raceSession(SessionType::Qualifying, $modern->round);
        $this->qualifying($this->car($qualifying, 1), 1, 90_000);
        $this->assertCount(0, $this->detect('early_bird', $modern));
    }

    public function test_a_surprise_pole_is_plot_twist_instead_of_early_bird(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $pole = $this->car($qualifying, 1);
        $this->earlierRace($qualifying->round, $pole->seasonTeam);
        $this->qualifying($pole, 1, 90_000, 89_000, 88_000);

        $this->runEngine($qualifying);

        $this->assertContains('plot_twist', $this->storedKeys());
        $this->assertNotContains('early_bird', $this->storedKeys());
    }

    // Early Chick

    public function test_early_chick_is_p1_in_sprint_qualifying(): void
    {
        $shootout = $this->raceSession(SessionType::SprintQualifying);
        $pole = $this->car($shootout, 1);
        $this->qualifying($pole, 1, 90_000);
        $this->qualifying($this->car($shootout, 2), 2, 90_100);

        $this->assertSame([$pole->id], $this->detect('early_chick', $shootout)->pluck('recipient.entryId')->all());
    }

    public function test_early_chick_is_not_the_grand_prix_pole(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $this->qualifying($this->car($qualifying, 1), 1, 90_000);

        $this->runEngine($qualifying);

        $this->assertContains('early_bird', $this->storedKeys());
        $this->assertNotContains('early_chick', $this->storedKeys());
    }

    public function test_early_chick_and_early_bird_stand_apart_on_one_weekend(): void
    {
        $round = $this->round();
        $driver = Driver::factory()->create();
        // No earlier race, so the team-form window is empty and neither pole is a surprise.
        $shootout = $this->raceSession(SessionType::SprintQualifying, $round);
        $qualifying = $this->raceSession(SessionType::Qualifying, $round);
        $this->qualifying($this->car($shootout, 1, null, $driver), 1, 90_000);
        $this->qualifying($this->car($qualifying, 1, null, $driver), 1, 89_000);

        $this->runEngine($shootout);
        $this->runEngine($qualifying);

        $this->assertSame(['early_bird', 'early_chick'], array_values(array_intersect($this->storedKeys(), ['early_bird', 'early_chick'])));
    }

    // Fun Size

    public function test_fun_size_is_the_sprint_podium(): void
    {
        $sprint = $this->raceSession(SessionType::Sprint);
        $first = $this->finish($sprint, 1, 1);
        $second = $this->finish($sprint, 2, 2);
        $third = $this->finish($sprint, 3, 3);

        $this->assertSame([$first->id], $this->detect('fun_size_win', $sprint)->pluck('recipient.entryId')->all());
        $this->assertSame([$second->id], $this->detect('fun_size_silver', $sprint)->pluck('recipient.entryId')->all());
        $this->assertSame([$third->id], $this->detect('fun_size_bronze', $sprint)->pluck('recipient.entryId')->all());
    }

    public function test_fun_size_misses_fourth_place_a_disqualification_and_the_grand_prix(): void
    {
        $sprint = $this->raceSession(SessionType::Sprint);
        $this->finish($sprint, 4, 4);
        $this->finish($sprint, 1, 1, ['status' => 'Disqualified', 'is_classified' => false]);
        $race = $this->raceSession(SessionType::Race, $sprint->round);
        $this->finish($race, 1, 1);

        foreach (['fun_size_win', 'fun_size_silver', 'fun_size_bronze'] as $key) {
            $this->assertCount(0, $this->detect($key, $sprint), $key);
        }

        // The engine looks for them in sprints only.
        $this->runEngine($race);
        $this->assertSame([], array_values(array_intersect($this->storedKeys(), ['fun_size_win', 'fun_size_silver', 'fun_size_bronze'])));
    }

    public function test_a_sprint_podium_is_no_longer_did_the_thing_silver_lining_or_solid_bronze(): void
    {
        $sprint = $this->raceSession(SessionType::Sprint);
        $race = $this->raceSession(SessionType::Race, $sprint->round);

        foreach ([1, 2, 3] as $position) {
            $this->finish($sprint, $position, $position);
            $this->finish($race, $position, $position);
        }

        $this->runEngine($sprint);
        $this->assertSame(['fun_size_bronze', 'fun_size_silver', 'fun_size_win'], array_values(array_intersect($this->storedKeys(), ['did_the_thing', 'silver_lining', 'solid_bronze', 'fun_size_win', 'fun_size_silver', 'fun_size_bronze'])));

        $this->runEngine($race);
        $this->assertSame(['did_the_thing', 'silver_lining', 'solid_bronze'], AchievementAward::query()->where('race_session_id', $race->id)->with('achievement')->get()->pluck('achievement.key')->intersect(['did_the_thing', 'silver_lining', 'solid_bronze'])->sort()->values()->all());
    }

    // Purple Patch

    public function test_purple_patch_is_the_fastest_lap_in_the_points(): void
    {
        $race = $this->raceSession();
        $this->finish($race, 1, 1, ['points' => 25]);
        $fastest = $this->finish($race, 2, 5, ['points' => 10, 'fastest_lap_rank' => 1]);

        $this->assertSame([$fastest->id], $this->detect('purple_patch', $race)->pluck('recipient.entryId')->all());
        $this->assertSame('P5', $this->detect('purple_patch', $race)->sole()->detail);
        $this->assertCount(0, $this->detect('all_dressed_up_nowhere_to_go', $race));
    }

    public function test_outside_the_points_the_fastest_lap_is_all_dressed_up_and_both_reach_back_to_1996(): void
    {
        $race = $this->raceSession(SessionType::Race, $this->round(1998));
        $winner = $this->finish($race, 1, 1, ['points' => 10]);
        $fastest = $this->finish($race, 2, 12, ['points' => 0]);
        LapTime::create(['driver_entry_id' => $winner->id, 'lap' => 1, 'position' => 1, 'time_millis' => 80_500]);
        LapTime::create(['driver_entry_id' => $fastest->id, 'lap' => 1, 'position' => 2, 'time_millis' => 80_100]);

        $this->assertCount(0, $this->detect('purple_patch', $race));
        $this->assertSame([$fastest->id], $this->detect('all_dressed_up_nowhere_to_go', $race)->pluck('recipient.entryId')->all());

        LapTime::query()->where('driver_entry_id', $fastest->id)->update(['time_millis' => 81_000]);
        $this->assertSame([$winner->id], $this->detect('purple_patch', $race)->pluck('recipient.entryId')->all());
    }

    public function test_all_dressed_up_in_a_sprint_does_not_wait_for_lap_times_jolpica_never_has(): void
    {
        $sprint = $this->raceSession(SessionType::Sprint, $this->round(2025));
        $this->finish($sprint, 1, 1, ['points' => 8]);
        $this->finish($sprint, 2, 12, ['points' => 0, 'fastest_lap_rank' => 1]);

        $this->runEngine($sprint, now()->toImmutable()->addYears(3));

        $award = $this->awards('all_dressed_up_nowhere_to_go')->sole();
        $this->assertSame([AwardStatus::Final, null], [$award->status, $award->review_reason]);
    }

    public function test_a_clean_sweep_replaces_purple_patch(): void
    {
        $race = $this->raceSession();
        $winner = $this->finish($race, 1, 1, ['points' => 25, 'fastest_lap_rank' => 1]);
        $this->finish($race, 2, 2, ['points' => 18]);

        foreach ([1, 2, 3] as $lap) {
            SessionLap::factory()->create(['race_session_id' => $race->id, 'driver_number' => 1, 'driver_entry_id' => $winner->id, 'lap_number' => $lap, 'started_at' => "2024-05-05 20:0{$lap}:00"]);
        }

        Position::factory()->create(['race_session_id' => $race->id, 'driver_number' => 1, 'driver_entry_id' => $winner->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => 1]);
        $this->assertCount(1, $this->detect('purple_patch', $race));

        $this->runEngine($race);

        $this->assertContains('clean_sweep', $this->storedKeys());
        $this->assertNotContains('purple_patch', $this->storedKeys());
        $this->assertNotContains('did_the_thing', $this->storedKeys());
    }

    // Encore

    public function test_encore_is_winning_the_sprint_and_the_grand_prix(): void
    {
        $round = $this->round();
        $sprint = $this->raceSession(SessionType::Sprint, $round);
        $race = $this->raceSession(SessionType::Race, $round);
        $driver = Driver::factory()->create();
        $this->finish($sprint, 1, 1, driver: $driver);
        $inTheRace = $this->finish($race, 1, 1, driver: $driver);

        $this->assertSame([$inTheRace->id], $this->detect('encore', $race)->pluck('recipient.entryId')->all());
    }

    public function test_encore_misses_a_driver_who_won_only_one_of_them(): void
    {
        $round = $this->round();
        $sprint = $this->raceSession(SessionType::Sprint, $round);
        $race = $this->raceSession(SessionType::Race, $round);
        $this->finish($sprint, 1, 1);
        $this->finish($race, 2, 1);

        $this->assertCount(0, $this->detect('encore', $race));
    }

    public function test_encore_stands_beside_fun_size_win_and_did_the_thing(): void
    {
        $round = $this->round();
        $sprint = $this->raceSession(SessionType::Sprint, $round);
        $race = $this->raceSession(SessionType::Race, $round);
        $driver = Driver::factory()->create();
        $this->finish($sprint, 1, 1, driver: $driver);
        $this->finish($race, 1, 1, driver: $driver);

        $this->runRound($round);

        $this->assertSame(['did_the_thing', 'encore', 'fun_size_win'], array_values(array_intersect($this->storedKeys(), ['did_the_thing', 'encore', 'fun_size_win'])));
        $this->assertSame(1, $this->awards('encore')->count());
    }

    // In Tandem

    public function test_in_tandem_is_a_one_two_for_the_team(): void
    {
        $race = $this->raceSession();
        $red = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $this->finish($race, 1, 1, team: $red);
        $this->finish($race, 11, 2, team: $red);

        $detection = $this->detect('in_tandem', $race)->sole();

        $this->assertSame([RecipientType::Team, $red->id], [$detection->recipient->type, $detection->recipient->seasonTeamId]);
    }

    public function test_in_tandem_misses_a_one_three_and_a_shared_winning_car(): void
    {
        $race = $this->raceSession(SessionType::Race, $this->round(1951));
        $red = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $this->finish($race, 8, 1, team: $red);
        $this->finish($race, 8, 1, team: $red);
        $this->finish($race, 4, 2);
        $this->finish($race, 9, 3, team: $red);

        $this->assertCount(0, $this->detect('in_tandem', $race));
    }

    public function test_in_tandem_stands_beside_the_drivers_own_awards(): void
    {
        $race = $this->raceSession();
        $red = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $this->finish($race, 1, 1, team: $red);
        $this->finish($race, 11, 2, team: $red);

        $this->runEngine($race);

        $this->assertSame(['did_the_thing', 'in_tandem', 'silver_lining'], array_values(array_intersect($this->storedKeys(), ['did_the_thing', 'in_tandem', 'silver_lining'])));
        $this->assertSame($red->id, $this->awards('in_tandem')->sole()->season_team_id);
    }

    // Hometown Hero

    private function raceAt(string $country, string $grandPrix): RaceSession
    {
        $circuit = Circuit::factory()->create(['country' => $country]);

        return $this->raceSession(SessionType::Race, $this->round(2024, 5, ['circuit_id' => $circuit->id, 'name' => $grandPrix]));
    }

    public function test_hometown_hero_is_a_win_in_the_drivers_own_country(): void
    {
        $race = $this->raceAt('Italy', 'Emilia Romagna Grand Prix');
        $winner = $this->finish($race, 16, 1, driver: Driver::factory()->create(['nationality' => 'Italian']));

        $this->assertSame([$winner->id], $this->detect('hometown_hero', $race)->pluck('recipient.entryId')->all());
    }

    public function test_hometown_hero_misses_a_win_abroad_and_a_podium_at_home(): void
    {
        $race = $this->raceAt('Italy', 'Italian Grand Prix');
        $this->finish($race, 44, 1, driver: Driver::factory()->create(['nationality' => 'British']));
        $this->finish($race, 16, 2, driver: Driver::factory()->create(['nationality' => 'Italian']));

        $this->assertCount(0, $this->detect('hometown_hero', $race));
    }

    public function test_hometown_hero_replaces_did_the_thing_and_stands_beside_giant_slayer(): void
    {
        $race = $this->raceAt('Monaco', 'Monaco Grand Prix');
        $winner = $this->finish($race, 16, 1, driver: Driver::factory()->create(['nationality' => 'Monegasque']));

        $this->runEngine($race);
        $this->assertContains('hometown_hero', $this->storedKeys());
        $this->assertNotContains('did_the_thing', $this->storedKeys());

        $this->earlierRace($race->round, $winner->seasonTeam);
        $this->runEngine($race);
        $this->assertSame(['giant_slayer', 'hometown_hero'], array_values(array_intersect($this->storedKeys(), ['did_the_thing', 'giant_slayer', 'hometown_hero'])));
    }
}
