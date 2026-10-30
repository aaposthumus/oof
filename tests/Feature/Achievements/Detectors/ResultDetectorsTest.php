<?php

namespace Tests\Feature\Achievements\Detectors;

use App\Achievements\Detectors\AllDressedUpDetector;
use App\Achievements\Detectors\DidTheThingDetector;
use App\Achievements\Detectors\EarlyLunchDetector;
use App\Achievements\Detectors\ForgotTheKeysDetector;
use App\Achievements\Detectors\GoodBoyDetector;
use App\Achievements\Detectors\HoggedTheLotDetector;
use App\Achievements\Detectors\HomeBeforeDinnerDetector;
use App\Achievements\Detectors\LappedDogDetector;
use App\Achievements\Detectors\LotteryTicketDetector;
use App\Achievements\Detectors\MathsIsOverDetector;
use App\Achievements\Detectors\PlotTwistDetector;
use App\Achievements\Detectors\ReverseGearDetector;
use App\Achievements\Detectors\SentToYourRoomDetector;
use App\Achievements\Detectors\SpareTyreDetector;
use App\Achievements\Detectors\StandingRoomOnlyDetector;
use App\Achievements\Detectors\TookTheScenicRouteDetector;
use App\Achievements\Detectors\TurnedOffTheLightsDetector;
use App\Enums\AwardConfidence;
use App\Enums\SessionType;
use App\Models\ConstructorStanding;
use App\Models\DriverEntry;
use App\Models\DriverStanding;
use App\Models\Position;
use App\Models\RaceSession;
use App\Models\Result;
use App\Models\SeasonTeam;
use App\Models\SessionLap;
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
        $this->assertSame([], $this->detect(StandingRoomOnlyDetector::class, $this->race)->where('driverEntryId', $disqualified->id)->all());
    }

    public function test_most_places_lost_last_finisher_and_fastest_lap_without_points(): void
    {
        $this->finisher(1, 1, ['points' => 25]);
        $dropped = $this->finisher(2, 2, ['grid' => 1, 'points' => 18]);
        $last = $this->finisher(3, 3, ['grid' => 2, 'points' => 0, 'fastest_lap_rank' => 1]);

        $this->assertSame([$dropped->id, $last->id], $this->detect(ReverseGearDetector::class, $this->race)->pluck('driverEntryId')->all());
        $this->assertSame($last->id, $this->detect(TurnedOffTheLightsDetector::class, $this->race)->sole()->driverEntryId);
        $this->assertSame($last->id, $this->detect(AllDressedUpDetector::class, $this->race)->sole()->driverEntryId);
    }

    public function test_win_podium_and_win_from_far_back(): void
    {
        $winner = $this->finisher(1, 1, ['grid' => 12]);
        $this->finisher(2, 2);
        $this->finisher(3, 3);
        $this->finisher(4, 4);

        $this->assertSame($winner->id, $this->detect(DidTheThingDetector::class, $this->race)->sole()->driverEntryId);
        $this->assertCount(3, $this->detect(StandingRoomOnlyDetector::class, $this->race));
        $this->assertSame('from P12', $this->detect(TookTheScenicRouteDetector::class, $this->race)->sole()->detail);
    }

    public function test_a_surprise_win_by_a_team_outside_the_top_four_before_the_round(): void
    {
        $previous = $this->round(2024, 4);
        $surpriseTeam = SeasonTeam::factory()->create(['season_id' => $previous->season_id]);
        $topTeam = SeasonTeam::factory()->create(['season_id' => $previous->season_id]);
        ConstructorStanding::create(['round_id' => $previous->id, 'season_team_id' => $topTeam->id, 'position' => 1, 'position_text' => '1', 'points' => 100, 'wins' => 4]);
        ConstructorStanding::create(['round_id' => $previous->id, 'season_team_id' => $surpriseTeam->id, 'position' => 7, 'position_text' => '7', 'points' => 6, 'wins' => 0]);
        $winner = $this->finisher(10, 1, ['team' => $surpriseTeam]);

        $this->assertSame("Team P7 in the constructors' standings before the round", $this->detect(LotteryTicketDetector::class, $this->race)->sole()->detail);

        $qualifying = $this->raceSession(SessionType::Qualifying, $this->race->round);
        $pole = $this->car($qualifying, 10, $surpriseTeam, $winner->driver);
        $this->qualifying($pole, 1, 90_000, 89_000, 88_000);
        $this->assertSame($pole->id, $this->detect(PlotTwistDetector::class, $qualifying)->sole()->driverEntryId);
    }

    public function test_grand_chelem_needs_pole_win_fastest_lap_and_every_lap_led(): void
    {
        $winner = $this->finisher(1, 1, ['fastest_lap_rank' => 1, 'laps_completed' => 3]);
        $this->finisher(2, 2, ['laps_completed' => 3]);

        foreach ([1, 2, 3] as $lap) {
            SessionLap::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 1, 'driver_entry_id' => $winner->id, 'lap_number' => $lap, 'started_at' => "2024-05-05 20:0{$lap}:00"]);
        }

        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 1, 'driver_entry_id' => $winner->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => 1]);
        $this->assertCount(1, $this->detect(HoggedTheLotDetector::class, $this->race));

        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 1, 'driver_entry_id' => $winner->id, 'recorded_at' => '2024-05-05 20:02:30', 'position' => 2]);
        $this->assertCount(0, $this->detect(HoggedTheLotDetector::class, $this->race));
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

        $this->assertSame($champion->id, $this->detect(MathsIsOverDetector::class, $this->race)->sole()->driverEntryId);
    }

    public function test_a_first_race_for_a_team_that_raced_before_is_a_low_confidence_call_up(): void
    {
        $earlier = $this->raceSession(SessionType::Race, $this->round(2024, 2));
        $team = SeasonTeam::factory()->create(['season_id' => $earlier->round->season_id]);
        $regular = $this->car($earlier, 55, $team);
        $this->finisher(55, 3, ['team' => $team])->update(['driver_id' => $regular->driver_id]);
        $standIn = $this->finisher(38, 7, ['team' => $team]);

        $detection = $this->detect(SpareTyreDetector::class, $this->race)->sole();

        $this->assertSame($standIn->id, $detection->driverEntryId);
        $this->assertSame(AwardConfidence::Low, $detection->confidence);
    }
}
