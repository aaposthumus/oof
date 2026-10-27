<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\SlowestPitStopExcavator;
use App\Models\Driver;
use App\Models\PitStop;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class SlowestPitStopExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.slowest_pit_stop.min_seconds' => 40,
            'excavators.slowest_pit_stop.max_seconds' => 180,
            'excavators.slowest_pit_stop.min_ratio' => 2.0,
            'excavators.slowest_pit_stop.red_flag_min_stops' => 3,
            'excavators.slowest_pit_stop.retire_within_laps' => 2,
        ]);
    }

    public function test_finds_long_stops_but_not_red_flags_garage_repairs_or_stops_before_retiring(): void
    {
        $season = Season::factory()->create(['year' => 2013]);
        $drivers = Driver::factory()->count(8)->create();
        $round = $this->createRaceWith($season, 1, [
            [$drivers[0], Result::factory()->classified(1)],
            [$drivers[1], Result::factory()->classified(2)],
            [$drivers[2], Result::factory()->classified(3)],
            [$drivers[3], Result::factory()->classified(4)],
            [$drivers[4], Result::factory()->classified(5)],
            [$drivers[5], Result::factory()->classified(6)],
            [$drivers[6], Result::factory()->classified(7)],
            [$drivers[7], Result::factory()->retired('Wheel nut')->state(['laps_completed' => 21])],
        ]);

        // Typical stops of 20 s, then: a loose wheel (162 s), a red flag (three
        // cars at 120 s), a garage repair (900 s) and a stop before retiring.
        foreach ($drivers->take(7) as $index => $driver) {
            $this->stop($round, $driver, 1, 15, 20_000);
            $this->stop($round, $driver, 2, 30, 20_000 + $index * 100);
        }

        $this->stop($round, $drivers[0], 3, 8, 162_042);
        $this->stop($round, $drivers[1], 3, 40, 120_000);
        $this->stop($round, $drivers[2], 3, 40, 121_000);
        $this->stop($round, $drivers[3], 3, 41, 122_000);
        $this->stop($round, $drivers[4], 3, 2, 900_000);
        $this->stop($round, $drivers[7], 1, 20, 123_000);

        $candidate = app(SlowestPitStopExcavator::class)->dig(new DigParameters)->sole();

        $this->assertSame([$round->id, $drivers[0]->id], [$candidate->roundId, $candidate->driverId]);
        $this->assertSame(8.1, $candidate->score);
        $this->assertSame(['duration_ms' => 162_042, 'lap' => 8, 'stop' => 3, 'typical_stop_ms' => 20_000, 'ratio' => 8.1, 'long_stops' => 1, 'status' => 'Finished'], $candidate->evidence);
    }

    private function stop(Round $round, Driver $driver, int $stop, int $lap, int $millis): void
    {
        $entry = $round->raceSessions()->sole()->driverEntries()->where('driver_id', $driver->id)->sole();

        PitStop::create(['driver_entry_id' => $entry->id, 'stop' => $stop, 'lap' => $lap, 'duration_millis' => $millis]);
    }
}
