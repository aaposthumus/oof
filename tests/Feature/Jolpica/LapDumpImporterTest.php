<?php

namespace Tests\Feature\Jolpica;

use App\Enums\SessionType;
use App\Jolpica\Dumps\JolpicaDump;
use App\Jolpica\Dumps\LapDumpImporter;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\LapTime;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsJolpicaDumps;
use Tests\TestCase;

class LapDumpImporterTest extends TestCase
{
    use BuildsJolpicaDumps, LazilyRefreshDatabase;

    public function test_writes_race_laps_onto_the_drivers_race_entry(): void
    {
        $norris = $this->raceEntry(1999, 3, 'norris');

        $this->import($this->dump());

        $laps = LapTime::where('driver_entry_id', $norris->id)->orderBy('lap')->get();
        $this->assertSame([1, 2], $laps->pluck('lap')->all());
        $this->assertSame([94_893, 3_661_044], $laps->pluck('time_millis')->all());
        $this->assertSame([4, null], $laps->pluck('position')->all());
    }

    public function test_leaves_out_sprint_laps_and_rows_without_a_lap_number(): void
    {
        $norris = $this->raceEntry(1999, 3, 'norris');
        $sprintEntry = DriverEntry::factory()->create([
            'driver_id' => $norris->driver_id,
            'race_session_id' => RaceSession::factory()->create(['round_id' => $norris->raceSession->round_id, 'type' => SessionType::Sprint])->id,
        ]);

        $report = $this->import($this->dump());

        $this->assertSame(0, LapTime::where('driver_entry_id', $sprintEntry->id)->count());
        $this->assertSame(['races' => 1, 'laps' => 2, 'unmatched_laps' => 1, 'missing_rounds' => ['2000-1']], $report);
    }

    public function test_importing_twice_keeps_the_same_rows(): void
    {
        $this->raceEntry(1999, 3, 'norris');

        $this->import($this->dump());
        $this->import($this->dump());

        $this->assertSame(2, LapTime::count());
    }

    public function test_imports_only_the_seasons_asked_for(): void
    {
        $this->raceEntry(1999, 3, 'norris');

        $report = $this->import($this->dump(), [2000]);

        $this->assertSame(0, LapTime::count());
        $this->assertSame(['2000-1'], $report['missing_rounds']);
    }

    /**
     * @param  list<int>  $years
     * @return array{races: int, laps: int, unmatched_laps: int, missing_rounds: list<string>}
     */
    private function import(JolpicaDump $dump, array $years = []): array
    {
        return app(LapDumpImporter::class)->import($dump, $years);
    }

    private function raceEntry(int $year, int $round, string $jolpicaDriverId): DriverEntry
    {
        $round = Round::factory()->for(Season::factory()->state(['year' => $year]))->create(['round_number' => $round]);

        return DriverEntry::factory()->create([
            'race_session_id' => RaceSession::factory()->create(['round_id' => $round->id])->id,
            'driver_id' => Driver::factory()->create(['jolpica_driver_id' => $jolpicaDriverId])->id,
        ]);
    }

    /**
     * 1999 round 3: Norris's two race laps, a fastest-lap row without a number,
     * a sprint lap, and a lap of a driver who is not in the results.
     * 2000 round 1: a race that is not in the database.
     */
    private function dump(): JolpicaDump
    {
        return $this->jolpicaDump([
            'season' => [['id' => 1, 'year' => 1999], ['id' => 2, 'year' => 2000]],
            'round' => [['id' => 10, 'season_id' => 1, 'number' => 3], ['id' => 11, 'season_id' => 2, 'number' => 1]],
            'session' => [
                ['id' => 100, 'round_id' => 10, 'type' => 'R'],
                ['id' => 101, 'round_id' => 10, 'type' => 'SR'],
                ['id' => 102, 'round_id' => 11, 'type' => 'R'],
            ],
            'driver' => [['id' => 1, 'reference' => 'norris'], ['id' => 2, 'reference' => 'nobody']],
            'teamdriver' => [['id' => 1, 'driver_id' => 1], ['id' => 2, 'driver_id' => 2]],
            'roundentry' => [['id' => 1, 'team_driver_id' => 1], ['id' => 2, 'team_driver_id' => 2], ['id' => 3, 'team_driver_id' => 1]],
            'sessionentry' => [
                ['id' => 1000, 'session_id' => 100, 'round_entry_id' => 1],
                ['id' => 1001, 'session_id' => 101, 'round_entry_id' => 1],
                ['id' => 1002, 'session_id' => 100, 'round_entry_id' => 2],
                ['id' => 1003, 'session_id' => 102, 'round_entry_id' => 3],
            ],
            'lap' => [
                ['session_entry_id' => 1000, 'number' => '2', 'position' => '', 'time' => '01:01:01.044'],
                ['session_entry_id' => 1001, 'number' => '1', 'position' => '1', 'time' => '00:01:30.000'],
                ['session_entry_id' => 1000, 'number' => '', 'position' => '', 'time' => '00:01:16.290'],
                ['session_entry_id' => 1000, 'number' => '1', 'position' => '4', 'time' => '00:01:34.893'],
                ['session_entry_id' => 1002, 'number' => '1', 'position' => '5', 'time' => '00:01:35.000'],
                ['session_entry_id' => 1003, 'number' => '1', 'position' => '1', 'time' => '00:01:20.000'],
            ],
        ]);
    }
}
