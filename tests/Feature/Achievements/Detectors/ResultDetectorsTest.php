<?php

namespace Tests\Feature\Achievements\Detectors;

use App\Achievements\Detectors\AllDressedUpDetector;
use App\Achievements\Detectors\ClassDismissedDetector;
use App\Achievements\Detectors\CleanSweepDetector;
use App\Achievements\Detectors\DidTheThingDetector;
use App\Achievements\Detectors\EarlyLunchDetector;
use App\Achievements\Detectors\ForgotTheKeysDetector;
use App\Achievements\Detectors\GiantSlayerDetector;
use App\Achievements\Detectors\GoodBoyDetector;
use App\Achievements\Detectors\HomeBeforeDinnerDetector;
use App\Achievements\Detectors\LappedDogDetector;
use App\Achievements\Detectors\PlotTwistDetector;
use App\Achievements\Detectors\ReverseGearDetector;
use App\Achievements\Detectors\SentToYourRoomDetector;
use App\Achievements\Detectors\SilverLiningDetector;
use App\Achievements\Detectors\SolidBronzeDetector;
use App\Achievements\Detectors\TookTheScenicRouteDetector;
use App\Achievements\Detectors\ToTheRescueDetector;
use App\Achievements\Detectors\TurnedOffTheLightsDetector;
use App\Achievements\Support\TeamForm;
use App\Enums\AwardConfidence;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use App\Models\DriverStanding;
use App\Models\Position;
use App\Models\RaceSession;
use App\Models\Result;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SessionLap;
use App\Models\Team;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class ResultDetectorsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private RaceSession $race;

    /**
     * A car in the race with a result; pass 'team' to set its team.
     */
    private function finisher(int $car, int $position, array $attributes = []): DriverEntry
    {
        $entry = $this->car($this->race, $car, $attributes['team'] ?? null);
        unset($attributes['team']);
        $this->resultFor($entry, ['position' => $position, 'position_text' => (string) $position, 'classification_order' => $position, 'grid' => $position, 'laps_completed' => 57, ...$attributes]);

        return $entry;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->race = $this->raceSession();
    }

    public function test_lapped_once_or_more_by_status_or_by_laps_behind_the_winner(): void
    {
        $this->finisher(1, 1);
        $once = $this->finisher(2, 2, ['status' => '+1 Lap', 'laps_completed' => 56]);
        $twice = $this->finisher(3, 3, ['status' => 'Lapped', 'laps_completed' => 55]);

        $this->assertSame([$once->id], $this->detect(LappedDogDetector::class, $this->race)->pluck('driverEntryId')->all());
        $this->assertSame('2 laps', $this->detect(GoodBoyDetector::class, $this->race)->sole()->detail);
        $this->assertSame($twice->id, $this->detect(GoodBoyDetector::class, $this->race)->sole()->driverEntryId);
    }

    public function test_retirements_on_lap_one_and_later(): void
    {
        $lapOne = $this->finisher(1, 0, ['position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => 'Collision', 'laps_completed' => 0]);
        $later = $this->finisher(2, 0, ['position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => 'Engine', 'laps_completed' => 30]);
        $this->finisher(3, 0, ['position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => 'Illness', 'laps_completed' => 3]);

        $this->assertSame([$lapOne->id], $this->detect(EarlyLunchDetector::class, $this->race)->pluck('driverEntryId')->all());
        $this->assertSame([$lapOne->id, $later->id], $this->detect(HomeBeforeDinnerDetector::class, $this->race)->pluck('driverEntryId')->all());
    }

    public function test_did_not_start_unless_through_illness(): void
    {
        $withdrew = $this->car($this->race, 1);
        Result::factory()->for($withdrew)->withdrawn()->create();
        $ill = $this->car($this->race, 2);
        Result::factory()->for($ill)->withdrawn()->create(['status' => 'Illness']);

        $this->assertSame([$withdrew->id], $this->detect(ForgotTheKeysDetector::class, $this->race)->pluck('driverEntryId')->all());
    }

    public function test_disqualified_whatever_the_position_text_says(): void
    {
        $this->finisher(1, 1);
        $disqualified = $this->finisher(44, 2, ['status' => 'Disqualified', 'is_classified' => false]);

        $detection = $this->detect(SentToYourRoomDetector::class, $this->race)->sole();
        $this->assertSame($disqualified->id, $detection->driverEntryId);
        $this->assertSame("disqualification:{$disqualified->id}", $detection->eventKey);
        $this->assertSame([], $this->detect(SilverLiningDetector::class, $this->race)->where('driverEntryId', $disqualified->id)->all());
    }

    public function test_most_places_lost_last_finisher_and_fastest_lap_without_points(): void
    {
        $this->finisher(1, 1, ['grid' => 2, 'points' => 25]);
        $this->finisher(2, 2, ['grid' => 3, 'points' => 18]);
        $last = $this->finisher(3, 3, ['grid' => 1, 'points' => 0, 'fastest_lap_rank' => 1]);

        $this->assertSame([$last->id], $this->detect(ReverseGearDetector::class, $this->race)->pluck('driverEntryId')->all());
        $this->assertSame($last->id, $this->detect(TurnedOffTheLightsDetector::class, $this->race)->sole()->driverEntryId);
        $this->assertSame($last->id, $this->detect(AllDressedUpDetector::class, $this->race)->sole()->driverEntryId);
    }

    public function test_a_shared_top_spot_for_most_places_lost_gives_no_award(): void
    {
        $this->finisher(1, 1, ['grid' => 3]);
        $this->finisher(2, 2, ['grid' => 1]);
        $this->finisher(3, 3, ['grid' => 2]);

        $this->assertCount(0, $this->detect(ReverseGearDetector::class, $this->race));
    }

    public function test_win_second_third_and_win_from_far_back(): void
    {
        $winner = $this->finisher(1, 1, ['grid' => 12]);
        $second = $this->finisher(2, 2);
        $third = $this->finisher(3, 3);
        $this->finisher(4, 4);

        $this->assertSame($winner->id, $this->detect(DidTheThingDetector::class, $this->race)->sole()->driverEntryId);
        $this->assertSame($second->id, $this->detect(SilverLiningDetector::class, $this->race)->sole()->driverEntryId);
        $this->assertSame($third->id, $this->detect(SolidBronzeDetector::class, $this->race)->sole()->driverEntryId);
        $this->assertSame('from P12', $this->detect(TookTheScenicRouteDetector::class, $this->race)->sole()->detail);
    }

    /**
     * A finished Grand Prix before the test race in which each given team scores the given points.
     *
     * @param  array<int, float>  $pointsByTeam  Keyed by season team ID
     */
    private function earlierRace(int $year, int $roundNumber, array $pointsByTeam, SessionType $type = SessionType::Race, string $name = 'Miami Grand Prix'): RaceSession
    {
        $round = $this->round($year, $roundNumber, ['name' => $name, 'jolpica_race_name' => $name]);
        $race = $this->raceSession($type, $round);

        foreach ($pointsByTeam as $seasonTeamId => $points) {
            $this->resultFor($this->car($race, 1, SeasonTeam::find($seasonTeamId)), ['points' => $points]);
        }

        return $race;
    }

    /**
     * Five lasting teams with a season team in both 2023 and 2024, best form first.
     *
     * @return list<array{2023: SeasonTeam, 2024: SeasonTeam}>
     */
    private function fiveTeams(): array
    {
        return collect(range(1, 5))->map(function () {
            $team = Team::factory()->create();

            return [
                2023 => SeasonTeam::factory()->create(['team_id' => $team->id, 'season_id' => Season::firstOrCreate(['year' => 2023])->id]),
                2024 => SeasonTeam::factory()->create(['team_id' => $team->id, 'season_id' => Season::firstOrCreate(['year' => 2024])->id]),
            ];
        })->all();
    }

    public function test_a_surprise_win_and_pole_by_a_team_outside_the_top_four_on_form(): void
    {
        $teams = $this->fiveTeams();
        $this->earlierRace(2024, 4, [$teams[0][2024]->id => 25, $teams[1][2024]->id => 18, $teams[2][2024]->id => 15, $teams[3][2024]->id => 12, $teams[4][2024]->id => 10]);
        $winner = $this->finisher(10, 1, ['team' => $teams[4][2024]]);

        $detection = $this->detect(GiantSlayerDetector::class, $this->race)->sole();
        $this->assertSame('Team P5 on form over the last race', $detection->detail);
        $this->assertStringContainsString('P5 with 10 points per race', $detection->evidence['note']);

        $qualifying = $this->raceSession(SessionType::Qualifying, $this->race->round);
        $pole = $this->car($qualifying, 10, $teams[4][2024], $winner->driver);
        $this->qualifying($pole, 1, 90_000, 89_000, 88_000);
        $this->assertSame('Team P5 on form over the last race', $this->detect(PlotTwistDetector::class, $qualifying)->sole()->detail);
    }

    public function test_a_win_by_a_top_four_team_on_form_is_no_surprise(): void
    {
        $teams = $this->fiveTeams();
        $this->earlierRace(2024, 4, [$teams[0][2024]->id => 25, $teams[1][2024]->id => 18, $teams[2][2024]->id => 15, $teams[3][2024]->id => 12, $teams[4][2024]->id => 10]);
        $this->finisher(10, 1, ['team' => $teams[3][2024]]);

        $this->assertCount(0, $this->detect(GiantSlayerDetector::class, $this->race));
    }

    public function test_in_round_one_the_window_reaches_into_the_previous_season_and_a_rename_keeps_the_form(): void
    {
        $this->race = $this->raceSession(SessionType::Race, $this->round(2024, 1));
        $teams = $this->fiveTeams();
        $this->earlierRace(2023, 22, [$teams[0][2023]->id => 1, $teams[1][2023]->id => 2, $teams[2][2023]->id => 3, $teams[3][2023]->id => 4, $teams[4][2023]->id => 5]);

        // The 2023 season team of the lasting team with the best form is a different (renamed) season team in 2024.
        $this->finisher(10, 1, ['team' => $teams[4][2024]]);
        $this->assertCount(0, $this->detect(GiantSlayerDetector::class, $this->race));

        Result::query()->whereHas('driverEntry', fn ($query) => $query->where('race_session_id', $this->race->id))->delete();
        $this->finisher(11, 1, ['team' => $teams[0][2024]]);
        $this->assertSame('Team P5 on form over the last race', $this->detect(GiantSlayerDetector::class, $this->race)->sole()->detail);
    }

    public function test_the_window_ranks_on_points_per_race_entered_and_skips_sprints_and_the_indianapolis_500(): void
    {
        $teams = $this->fiveTeams();
        $ids = array_map(fn (array $team) => $team[2024]->id, $teams);
        // Team 4 entered one race only and scored 20 in it: 20 per race beats team 0 with 30 over two.
        $this->earlierRace(2024, 1, [$ids[0] => 15, $ids[1] => 6, $ids[2] => 4, $ids[3] => 2]);
        $this->earlierRace(2024, 2, [$ids[0] => 15, $ids[1] => 6, $ids[2] => 4, $ids[3] => 2, $ids[4] => 20]);
        $this->earlierRace(2024, 3, [$ids[3] => 100], SessionType::Sprint);
        $this->earlierRace(2024, 4, [$ids[3] => 100], name: 'Indianapolis 500');

        $form = TeamForm::before($this->race->round);

        $this->assertCount(2, $form->races);
        $this->assertSame(1, $form->rankOf($teams[4][2024]->team_id));
        $this->assertSame(2, $form->rankOf($teams[0][2024]->team_id));
        $this->assertSame(5, $form->rankOf($teams[3][2024]->team_id));
        $this->assertNull($form->rankOf(Team::factory()->create()->id));
    }

    public function test_equal_form_shares_the_better_rank(): void
    {
        $teams = $this->fiveTeams();
        $ids = array_map(fn (array $team) => $team[2024]->id, $teams);
        $this->earlierRace(2024, 4, [$ids[0] => 25, $ids[1] => 18, $ids[2] => 15, $ids[3] => 10, $ids[4] => 10]);

        $this->finisher(10, 1, ['team' => $teams[4][2024]]);

        $this->assertSame(4, TeamForm::before($this->race->round)->rankOf($teams[4][2024]->team_id));
        $this->assertCount(0, $this->detect(GiantSlayerDetector::class, $this->race));
    }

    public function test_a_team_without_a_race_in_the_window_is_a_surprise_but_the_first_race_ever_is_not(): void
    {
        $this->finisher(10, 1);
        $this->assertCount(0, $this->detect(GiantSlayerDetector::class, $this->race));

        $teams = $this->fiveTeams();
        $this->earlierRace(2024, 4, [$teams[0][2024]->id => 25]);

        $this->assertSame('No race for the team in the last race', $this->detect(GiantSlayerDetector::class, $this->race)->sole()->detail);
    }

    public function test_grand_chelem_needs_pole_win_fastest_lap_and_every_lap_led(): void
    {
        $winner = $this->finisher(1, 1, ['fastest_lap_rank' => 1, 'laps_completed' => 3]);
        $this->finisher(2, 2, ['laps_completed' => 3]);

        foreach ([1, 2, 3] as $lap) {
            SessionLap::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 1, 'driver_entry_id' => $winner->id, 'lap_number' => $lap, 'started_at' => "2024-05-05 20:0{$lap}:00"]);
        }

        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 1, 'driver_entry_id' => $winner->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => 1]);
        $this->assertCount(1, $this->detect(CleanSweepDetector::class, $this->race));

        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 1, 'driver_entry_id' => $winner->id, 'recorded_at' => '2024-05-05 20:02:30', 'position' => 2]);
        $this->assertCount(0, $this->detect(CleanSweepDetector::class, $this->race));
    }

    public function test_title_clinched_when_the_lead_beats_the_points_left(): void
    {
        $previous = $this->round(2024, 4);
        $champion = $this->finisher(1, 1, ['points' => 25]);
        $rival = $this->finisher(4, 2, ['points' => 18]);
        $this->round(2024, 6);
        $this->raceSession(SessionType::Race, $this->round(2024, 7));
        DriverStanding::create(['round_id' => $previous->id, 'driver_id' => $champion->driver_id, 'position' => 1, 'position_text' => '1', 'points' => 100, 'wins' => 4]);
        DriverStanding::create(['round_id' => $previous->id, 'driver_id' => $rival->driver_id, 'position' => 2, 'position_text' => '2', 'points' => 80, 'wins' => 0]);
        DriverStanding::create(['round_id' => $this->race->round_id, 'driver_id' => $champion->driver_id, 'position' => 1, 'position_text' => '1', 'points' => 125, 'wins' => 5]);
        DriverStanding::create(['round_id' => $this->race->round_id, 'driver_id' => $rival->driver_id, 'position' => 2, 'position_text' => '2', 'points' => 98, 'wins' => 0]);

        $this->assertSame($champion->id, $this->detect(ClassDismissedDetector::class, $this->race)->sole()->driverEntryId);
    }

    public function test_a_driver_without_an_earlier_start_that_season_is_a_genuine_call_up(): void
    {
        $earlier = $this->raceSession(SessionType::Race, $this->round(2024, 2));
        $team = SeasonTeam::factory()->create(['season_id' => $earlier->round->season_id]);
        $regular = $this->car($earlier, 55, $team);
        $this->finisher(55, 3, ['team' => $team])->update(['driver_id' => $regular->driver_id]);
        $standIn = $this->finisher(38, 7, ['team' => $team]);

        $detection = $this->detect(ToTheRescueDetector::class, $this->race)->sole();

        $this->assertSame($standIn->id, $detection->driverEntryId);
        $this->assertSame(AwardConfidence::High, $detection->confidence);
    }

    public function test_a_seat_swap_stays_low_confidence(): void
    {
        $earlier = $this->raceSession(SessionType::Race, $this->round(2024, 2));
        $team = SeasonTeam::factory()->create(['season_id' => $earlier->round->season_id]);
        $this->resultFor($this->car($earlier, 55, $team), ['position' => 3, 'position_text' => '3']);
        $swapper = $this->finisher(38, 7, ['team' => $team]);
        $otherTeamEntry = $this->car($earlier, 40);
        $this->resultFor($otherTeamEntry, ['position' => 9, 'position_text' => '9']);
        $otherTeamEntry->update(['driver_id' => $swapper->driver_id]);

        $detection = $this->detect(ToTheRescueDetector::class, $this->race)->sole();

        $this->assertSame($swapper->id, $detection->driverEntryId);
        $this->assertSame(AwardConfidence::Low, $detection->confidence);
    }
}
