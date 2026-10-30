<?php

namespace Tests\Feature\Achievements\Detectors;

use App\Achievements\Detectors\ByAWhiskerDetector;
use App\Achievements\Detectors\CalledInAgainDetector;
use App\Achievements\Detectors\FamilyFeudDetector;
use App\Achievements\Detectors\FineDiningDetector;
use App\Achievements\Detectors\FrequentFlyerDetector;
use App\Achievements\Detectors\HoldingTheDoorDetector;
use App\Achievements\Detectors\OvertimeDetector;
use App\Achievements\Detectors\PracticeMakesImperfectDetector;
use App\Achievements\Detectors\SoCloseDetector;
use App\Achievements\Detectors\TableForOneDetector;
use App\Achievements\Detectors\TookTheLeadDetector;
use App\Achievements\Detectors\VirtuallyThereDetector;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use App\Models\PitLaneVisit;
use App\Models\RaceSession;
use App\Models\SeasonTeam;
use App\Models\SessionLap;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class SessionDetectorsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    /**
     * A 20-car qualifying: P1-P10 reach Q3, P11-P15 go out in Q2, P16-P20 in Q1.
     *
     * @param  array<int, array{q1?: int|null, q2?: int|null, q3?: int|null, team?: SeasonTeam}>  $overrides  Times or team by position
     * @return array<int, DriverEntry> By position
     */
    private function qualifyingField(RaceSession $qualifying, array $overrides = []): array
    {
        $entries = [];

        foreach (range(1, 20) as $position) {
            $car = [
                'q1' => 90_000 + $position * 100,
                'q2' => $position <= 15 ? 89_000 + $position * 100 : null,
                'q3' => $position <= 10 ? 88_000 + $position * 100 : null,
                'team' => null,
                ...$overrides[$position] ?? [],
            ];
            $entries[$position] = $this->car($qualifying, $position + 1, $car['team']);
            $this->qualifying($entries[$position], $position, $car['q1'], $car['q2'], $car['q3']);
        }

        return $entries;
    }

    public function test_family_feud_when_the_last_car_through_is_the_teammate(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $team = SeasonTeam::factory()->create(['season_id' => $qualifying->round->season_id]);
        $field = $this->qualifyingField($qualifying, [15 => ['team' => $team], 16 => ['team' => $team]]);

        $detection = $this->detect(FamilyFeudDetector::class, $qualifying)->sole();

        $this->assertSame($field[16]->id, $detection->driverEntryId);
        $this->assertSame('Q1', $detection->detail);
    }

    public function test_by_a_whisker_counts_from_the_last_car_through_and_never_below_it(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $field = $this->qualifyingField($qualifying, [11 => ['q2' => 90_012], 12 => ['q2' => 89_990], 13 => ['q2' => 90_015]]);

        $detection = $this->detect(ByAWhiskerDetector::class, $qualifying)->sole();

        $this->assertSame($field[11]->id, $detection->driverEntryId);
        $this->assertSame('Q2 · +0.012 s', $detection->detail);
    }

    public function test_a_car_through_without_a_time_still_took_the_last_place_through(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $field = $this->qualifyingField($qualifying, [10 => ['q3' => null], 11 => ['q2' => 90_010]]);

        $detection = $this->detect(ByAWhiskerDetector::class, $qualifying)->sole();

        $this->assertSame($field[11]->id, $detection->driverEntryId);
        $this->assertSame('Q2 · +0.010 s', $detection->detail);
    }

    public function test_holding_the_door_is_the_slowest_q1_time(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $field = $this->qualifyingField($qualifying);

        $this->assertSame($field[20]->id, $this->detect(HoldingTheDoorDetector::class, $qualifying)->sole()->driverEntryId);
    }

    public function test_so_close_when_a_deleted_q3_time_beats_the_classified_one(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $field = $this->qualifyingField($qualifying);
        $this->message($qualifying, 'SESSION STARTED', ['qualifying_phase' => 3]);
        $this->message($qualifying, 'CAR 2 (VER) TIME 1:27.950 DELETED - TRACK LIMITS AT TURN 4 LAP 17 19:04:00');
        $this->message($qualifying, 'CAR 2 (VER) TIME 1:28.500 DELETED - TRACK LIMITS AT TURN 4 LAP 19 19:06:00');

        $detection = $this->detect(SoCloseDetector::class, $qualifying)->sole();

        $this->assertSame($field[1]->id, $detection->driverEntryId);
        $this->assertSame('1:27.950', $detection->detail);
    }

    public function test_practice_achievements_only_count_race_drivers(): void
    {
        $race = $this->raceSession();
        $practice = $this->raceSession(SessionType::Practice1, $race->round);
        $regular = $this->car($practice, 16);
        $rookie = $this->car($practice, 38);
        $this->car($race, 16, null, $regular->driver);

        foreach ([[$regular, 25], [$rookie, 30]] as [$entry, $laps]) {
            foreach (range(1, $laps) as $lap) {
                SessionLap::factory()->create(['race_session_id' => $practice->id, 'driver_number' => $entry->car_number, 'driver_entry_id' => $entry->id, 'lap_number' => $lap]);
            }
        }

        foreach (['1:30.000', '1:30.100', '1:30.200'] as $time) {
            $this->message($practice, "CAR 16 (LEC) TIME {$time} DELETED - TRACK LIMITS AT TURN 4 LAP 3 13:10:00");
        }

        foreach (['1:30.500', '1:30.600', '1:30.700', '1:30.800'] as $time) {
            $this->message($practice, "CAR 38 (BEA) TIME {$time} DELETED - TRACK LIMITS AT TURN 4 LAP 4 13:11:00");
        }

        $this->assertSame('25 laps', $this->detect(OvertimeDetector::class, $practice)->sole()->detail);
        $this->assertSame('3 deleted laps', $this->detect(PracticeMakesImperfectDetector::class, $practice)->sole()->detail);
    }

    public function test_a_shared_top_spot_or_too_few_deleted_laps_gives_no_practice_award(): void
    {
        $race = $this->raceSession();
        $practice = $this->raceSession(SessionType::Practice2, $race->round);

        foreach ([16, 55] as $number) {
            $entry = $this->car($practice, $number);
            $this->car($race, $number, null, $entry->driver);

            foreach (range(1, 20) as $lap) {
                SessionLap::factory()->create(['race_session_id' => $practice->id, 'driver_number' => $number, 'driver_entry_id' => $entry->id, 'lap_number' => $lap]);
            }
        }

        $this->message($practice, 'CAR 16 (LEC) TIME 1:30.000 DELETED - TRACK LIMITS AT TURN 4 LAP 3 13:10:00');
        $this->message($practice, 'CAR 16 (LEC) TIME 1:30.100 DELETED - TRACK LIMITS AT TURN 4 LAP 5 13:12:00');

        $this->assertCount(0, $this->detect(OvertimeDetector::class, $practice));
        $this->assertCount(0, $this->detect(PracticeMakesImperfectDetector::class, $practice));
    }

    public function test_a_shared_slowest_q1_time_gives_no_award(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $this->qualifyingField($qualifying, [19 => ['q1' => 92_000], 20 => ['q1' => 92_000]]);

        $this->assertCount(0, $this->detect(HoldingTheDoorDetector::class, $qualifying));
    }

    public function test_slow_stops_by_measured_stationary_time_only(): void
    {
        $race = $this->raceSession();
        $sprint = $this->raceSession(SessionType::Sprint, $race->round);
        $car = $this->car($race, 44);
        $sprintCar = $this->car($sprint, 44, null, $car->driver);
        $slow = PitLaneVisit::factory()->create(['race_session_id' => $race->id, 'driver_number' => 44, 'driver_entry_id' => $car->id, 'entered_at' => '2024-05-05 20:20:00', 'stop_duration_millis' => 6_200, 'lane_duration_millis' => 25_000]);
        PitLaneVisit::factory()->create(['race_session_id' => $race->id, 'driver_number' => 44, 'driver_entry_id' => $car->id, 'entered_at' => '2024-05-05 20:50:00', 'stop_duration_millis' => null, 'lane_duration_millis' => 45_000]);
        PitLaneVisit::factory()->create(['race_session_id' => $sprint->id, 'driver_number' => 44, 'driver_entry_id' => $sprintCar->id, 'entered_at' => '2024-05-04 16:20:00', 'stop_duration_millis' => 3_000, 'lane_duration_millis' => 22_000]);

        $this->assertSame('6.2 s', $this->detect(TableForOneDetector::class, $race)->sole()->detail);
        $this->assertSame("pit:{$slow->id}", $this->detect(FineDiningDetector::class, $race)->sole()->eventKey);
        $this->assertCount(0, $this->detect(FineDiningDetector::class, $sprint));
    }

    public function test_a_stop_that_served_a_penalty_is_not_a_slow_stop(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 44);
        PitLaneVisit::factory()->create(['race_session_id' => $race->id, 'driver_number' => 44, 'driver_entry_id' => $car->id, 'entered_at' => '2024-05-05 20:00:30', 'stop_duration_millis' => 7_400, 'lane_duration_millis' => 26_000]);
        $this->message($race, 'FIA STEWARDS: PENALTY SERVED - 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');

        $this->assertCount(0, $this->detect(TableForOneDetector::class, $race));
    }

    public function test_safety_car_corner(): void
    {
        config(['achievements.took_the_lead_laps' => 8, 'achievements.called_in_again' => 2]);
        $race = $this->raceSession();
        $this->car($race, 1);
        $this->resultFor(DriverEntry::first(), []);
        $this->message($race, 'SAFETY CAR DEPLOYED', ['lap_number' => 3]);
        $this->message($race, 'SAFETY CAR IN THIS LAP', ['lap_number' => 7]);
        $this->message($race, 'VIRTUAL SAFETY CAR DEPLOYED', ['lap_number' => 20]);
        $this->message($race, 'SAFETY CAR DEPLOYED', ['lap_number' => 40]);
        $this->message($race, 'SAFETY CAR IN THIS LAP', ['lap_number' => 43]);

        $this->assertSame('9 laps', $this->detect(TookTheLeadDetector::class, $race)->sole()->detail);
        $this->assertSame('2 deployments', $this->detect(CalledInAgainDetector::class, $race)->sole()->detail);
        $this->assertSame('lap 20', $this->detect(VirtuallyThereDetector::class, $race)->sole()->detail);
        $this->assertSame('2 deployments', $this->detect(FrequentFlyerDetector::class, $race)->sole()->detail);
        $this->assertNull($this->detect(VirtuallyThereDetector::class, $race)->sole()->driverEntryId);
    }
}
