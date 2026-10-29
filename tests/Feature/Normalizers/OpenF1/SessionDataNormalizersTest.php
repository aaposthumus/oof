<?php

namespace Tests\Feature\Normalizers\OpenF1;

use App\Models\Driver;
use App\Models\OpenF1SessionDriver;
use App\Models\PitLaneVisit;
use App\Models\RaceControlMessage;
use App\Models\RaceSession;
use App\Models\SessionLap;
use App\Models\Stint;
use App\Models\TeamRadioMessage;
use App\Models\WeatherSample;
use App\Normalizers\Jolpica\RacesNormalizer;
use App\Normalizers\MissingDependencyException;
use App\Normalizers\OpenF1\DriversNormalizer;
use App\Normalizers\OpenF1\LapsNormalizer;
use App\Normalizers\OpenF1\MeetingsNormalizer;
use App\Normalizers\OpenF1\PitNormalizer;
use App\Normalizers\OpenF1\RaceControlNormalizer;
use App\Normalizers\OpenF1\SessionsNormalizer;
use App\Normalizers\OpenF1\StintsNormalizer;
use App\Normalizers\OpenF1\TeamRadioNormalizer;
use App\Normalizers\OpenF1\WeatherNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesOpenF1Fixtures;
use Tests\TestCase;

class SessionDataNormalizersTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesOpenF1Fixtures;

    public function test_keeps_race_control_messages_and_links_the_car_named_in_the_text(): void
    {
        $race = $this->austinRace();
        $this->normalizeOpenF1(RaceControlNormalizer::class, 'race_control_11396');

        $deletion = RaceControlMessage::where('message', 'like', 'CAR 5 (BOR) TIME 1:53.937 DELETED%')->sole();
        $incident = RaceControlMessage::where('message', 'like', 'TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) NOTED%')->sole();
        $blueFlag = RaceControlMessage::where('flag', 'BLUE')->first();

        $this->assertSame(count($this->openF1Fixture('race_control_11396')), $race->raceControlMessages()->count());
        $this->assertNull($deletion->driver_number);
        $this->assertSame(5, $deletion->car_number);
        $this->assertSame('bortoleto', $deletion->driverEntry->driver->jolpica_driver_id);
        $this->assertSame([87, 10], $incident->car_numbers);
        $this->assertNull($incident->driver_entry_id);
        $this->assertSame($blueFlag->driver_number, $blueFlag->driverEntry->car_number);
    }

    public function test_parses_the_cars_a_message_names(): void
    {
        $this->assertSame([87, 10], RaceControlNormalizer::carNumbers('FIA STEWARDS: TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) UNDER INVESTIGATION - CAUSING A COLLISION (15:06:57)'));
        $this->assertSame([23, 31, 27], RaceControlNormalizer::carNumbers('FIA STEWARDS: Q1 INCIDENT INVOLVING CARS 23 (ALB), 31 (OCO), 27 (HUL) REVIEWED NO FURTHER INVESTIGATION'));
        $this->assertSame([14], RaceControlNormalizer::carNumbers('CAR 14 (ALO) LAP DELETED - TRACK LIMITS AT TURN 4 LAP 34 18:58:59 (PIT)'));
        $this->assertSame([16], RaceControlNormalizer::carNumbers('CAR LEC TIME 1:36.518 DELETED - TRACK LIMITS AT TURN 14 (NEXT LAP) (Q1)', ['LEC' => 16]));
        $this->assertSame([], RaceControlNormalizer::carNumbers('CAR LEC TIME 1:36.518 DELETED - TRACK LIMITS AT TURN 14 (NEXT LAP) (Q1)'));
    }

    public function test_drops_race_control_of_the_support_series_session_that_follows(): void
    {
        $race = $this->austinRace();
        $messages = $this->openF1Fixture('race_control_11396');
        // 2023 British GP FP1: after the flag, OpenF1's feed ran on into F3 qualifying and its red flag.
        $spillover = [
            ['message' => 'RISK OF RAIN FOR F3 QUALIFYING SESSION IS 0%', 'date' => '2026-10-25T22:05:12+00:00', 'category' => 'Other', 'flag' => null],
            ['message' => 'RED FLAG', 'date' => '2026-10-25T22:06:47+00:00', 'category' => 'Flag', 'flag' => 'RED'],
        ];

        app(RaceControlNormalizer::class)->normalize([...$messages, ...array_map(fn (array $message) => [...$messages[0], 'driver_number' => null, ...$message], $spillover)]);

        $this->assertSame(count($messages), $race->raceControlMessages()->count());
        $this->assertFalse($race->raceControlMessages()->where('flag', 'RED')->exists());
    }

    public function test_renormalizing_race_control_leaves_the_same_rows(): void
    {
        $this->austinRace();
        $this->normalizeOpenF1(RaceControlNormalizer::class, 'race_control_11396');
        $ids = RaceControlMessage::orderBy('id')->pluck('id')->all();

        $this->normalizeOpenF1(RaceControlNormalizer::class, 'race_control_11396');

        $this->assertSame($ids, RaceControlMessage::orderBy('id')->pluck('id')->all());
    }

    public function test_removes_rows_openf1_no_longer_lists(): void
    {
        $race = $this->austinRace();
        $this->normalizeOpenF1(StintsNormalizer::class, 'stints_11396');
        $stints = $this->openF1Fixture('stints_11396');

        app(StintsNormalizer::class)->normalize(array_slice($stints, 1));

        $this->assertSame(count($stints) - 1, Stint::where('race_session_id', $race->id)->count());
    }

    public function test_stores_laps_stints_weather_and_team_radio(): void
    {
        $race = $this->austinRace();
        $this->normalizeOpenF1(LapsNormalizer::class, 'laps_11396');
        $this->normalizeOpenF1(StintsNormalizer::class, 'stints_11396');
        $this->normalizeOpenF1(WeatherNormalizer::class, 'weather_11396');
        $this->normalizeOpenF1(TeamRadioNormalizer::class, 'team_radio_11396');

        $lap = SessionLap::where('driver_number', 12)->where('lap_number', 1)->sole();

        $this->assertSame(100_364, $lap->duration_millis);
        $this->assertSame(27_316, $lap->sector_1_millis);
        $this->assertSame('2026-10-25 20:03:49.706', $lap->started_at->format('Y-m-d H:i:s.v'));
        $this->assertSame('antonelli', $lap->driverEntry->driver->jolpica_driver_id);
        $this->assertSame(0, Stint::whereNull('driver_entry_id')->count());
        $this->assertSame(5, WeatherSample::where('race_session_id', $race->id)->count());
        $this->assertFalse(WeatherSample::first()->rainfall);
        $this->assertSame(4, TeamRadioMessage::whereNotNull('driver_entry_id')->count());
    }

    public function test_links_race_pit_lane_visits_to_jolpica_pit_stops(): void
    {
        $this->austinRace();
        $this->normalizeOpenF1(PitNormalizer::class, 'pit_11396');

        $visit = PitLaneVisit::where('driver_number', 3)->where('lap_number', 13)->sole();

        $this->assertSame(count($this->openF1Fixture('pit_11396')), PitLaneVisit::whereNotNull('pit_stop_id')->count());
        $this->assertSame($visit->driver_entry_id, $visit->pitStop->driver_entry_id);
        $this->assertSame(23_900, $visit->lane_duration_millis);
    }

    public function test_links_rows_of_a_car_once_it_is_matched(): void
    {
        $this->matchSeason2026();
        $this->normalizeOpenF1(DriversNormalizer::class, 'drivers_11396');
        OpenF1SessionDriver::where('driver_number', 12)->update(['driver_entry_id' => null]);
        $this->normalizeOpenF1(LapsNormalizer::class, 'laps_11396');
        $this->assertSame(3, SessionLap::where('driver_number', 12)->whereNull('driver_entry_id')->count());

        $this->normalizeOpenF1(DriversNormalizer::class, 'drivers_11396');

        $this->assertSame(0, SessionLap::whereNull('driver_entry_id')->count());
    }

    public function test_an_override_links_a_stand_in(): void
    {
        $this->matchSeason2026();
        Driver::factory()->create(['jolpica_driver_id' => 'herta', 'given_name' => 'Colton', 'family_name' => 'Herta']);
        config(['openf1.driver_overrides' => ['2026:25' => 'herta']]);

        $this->normalizeOpenF1(DriversNormalizer::class, 'drivers_11389');

        $herta = OpenF1SessionDriver::where('driver_number', 25)->sole();
        $this->assertSame('herta', $herta->driver->jolpica_driver_id);
        $this->assertSame('cadillac', $herta->seasonTeam->jolpica_constructor_id);
    }

    public function test_defers_race_cars_until_jolpica_has_the_race_entries(): void
    {
        $this->normalizeOpenF1(RacesNormalizer::class, 'jolpica_races_2026');
        $this->normalizeOpenF1(MeetingsNormalizer::class, 'meetings_2026');
        $this->normalizeOpenF1(SessionsNormalizer::class, 'sessions_2026');

        $this->expectException(MissingDependencyException::class);

        $this->normalizeOpenF1(DriversNormalizer::class, 'drivers_11396');
    }

    public function test_defers_session_data_until_the_cars_are_normalized(): void
    {
        $this->matchSeason2026();

        $this->expectException(MissingDependencyException::class);

        $this->normalizeOpenF1(LapsNormalizer::class, 'laps_11396');
    }

    public function test_defers_session_data_of_an_unmatched_session(): void
    {
        $this->expectException(MissingDependencyException::class);

        $this->normalizeOpenF1(RaceControlNormalizer::class, 'race_control_11396');
    }

    public function test_an_empty_response_changes_nothing(): void
    {
        app(WeatherNormalizer::class)->normalize([]);

        $this->assertSame(0, RaceSession::count());
    }
}
