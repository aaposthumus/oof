<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Enums\SessionType;
use App\Excavation\DigParameters;
use App\Excavation\Excavators\AgeRecordExcavator;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\QualifyingResult;
use App\Models\RaceSession;
use App\Models\Result;
use App\Models\Season;
use App\Models\SeasonTeam;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class AgeRecordExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['excavators.age_record.top' => 1]);
    }

    public function test_merges_the_records_a_driver_set_in_one_race(): void
    {
        $season = Season::factory()->create(['year' => 2016]);
        $young = Driver::factory()->create(['date_of_birth' => '1997-09-30']);
        $old = Driver::factory()->create(['date_of_birth' => '1970-01-01']);
        $youngest = $this->createRaceWith($season, 1, [
            [$young, Result::factory()->classified(1)->fromGrid(1)],
            [$old, Result::factory()->classified(2)->fromGrid(2)],
        ]);
        $oldest = $this->createRaceWith($season, 2, [
            [$old, Result::factory()->classified(1)->fromGrid(1)],
            [$young, Result::factory()->classified(2)->fromGrid(2)],
        ]);

        $candidates = app(AgeRecordExcavator::class)->dig(new DigParameters)->keyBy('driverId');

        $this->assertSame($youngest->id, $candidates[$young->id]->roundId);
        $this->assertSame('youngest winner #1, youngest pole sitter #1, youngest podium finisher #1', $candidates[$young->id]->evidence['records']);
        $this->assertSame(18, $candidates[$young->id]->evidence['age_years']);
        $this->assertTrue($candidates[$young->id]->evidence['all_time_record']);
        $this->assertSame(100.0, $candidates[$young->id]->score);
        $this->assertSame($oldest->id, $candidates[$old->id]->roundId);
        $this->assertSame('oldest winner', $candidates[$old->id]->evidence['best_record']);
    }

    public function test_pole_comes_from_qualifying_where_it_exists_and_never_from_a_shared_car(): void
    {
        $season = Season::factory()->create(['year' => 1995]);
        $young = Driver::factory()->create(['date_of_birth' => '1975-01-01']);
        $old = Driver::factory()->create(['date_of_birth' => '1950-01-01']);
        $round = $this->createRaceWith($season, 1, [
            [$young, Result::factory()->classified(2)->fromGrid(1)],
            [$old, Result::factory()->classified(4)->fromGrid(2)],
        ]);
        $qualifying = RaceSession::factory()->for($round)->create(['type' => SessionType::Qualifying]);
        QualifyingResult::create(['driver_entry_id' => DriverEntry::factory()->for($qualifying)->for($old)->create()->id, 'position' => 1]);

        $shared = Season::factory()->create(['year' => 1951]);
        $team = SeasonTeam::factory()->for($shared)->create();
        $sharedRound = $this->createRaceWith($shared, 1, [[Driver::factory()->create(['date_of_birth' => '1898-06-09']), Result::factory()->classified(5)->fromGrid(1), $team]]);
        Result::factory()->classified(5)->fromGrid(1)->for(DriverEntry::factory()->for($sharedRound->raceSessions()->sole())->for($team)->state(['car_number' => 1]))->create();

        $records = app(AgeRecordExcavator::class)->dig(new DigParameters)->pluck('evidence.records', 'driverId');

        $this->assertSame('youngest pole sitter #1, oldest pole sitter #1', $records[$old->id]);
        $this->assertStringNotContainsString('pole', $records[$young->id] ?? '');
        $this->assertSame(1, $records->filter(fn (string $record) => str_contains($record, 'pole'))->count());
    }
}
