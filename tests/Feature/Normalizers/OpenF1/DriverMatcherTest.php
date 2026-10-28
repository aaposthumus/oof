<?php

namespace Tests\Feature\Normalizers\OpenF1;

use App\Enums\OpenF1MatchStatus;
use App\Enums\SessionType;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\OpenF1SessionDriver;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Normalizers\OpenF1\DriversNormalizer;
use App\Normalizers\OpenF1\Support\DriverMatch;
use App\Normalizers\OpenF1\Support\DriverMatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesOpenF1Fixtures;
use Tests\TestCase;

class DriverMatcherTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesOpenF1Fixtures;

    private Season $season;

    private RaceSession $practice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['year' => 2024]);
        $round = Round::factory()->for($this->season)->create(['round_number' => 24]);
        $this->practice = RaceSession::factory()->for($round)->create(['type' => SessionType::Practice1]);

        foreach (['mclaren', 'sauber', 'haas', 'alpine', 'red_bull', 'aston_martin'] as $constructor) {
            SeasonTeam::factory()->for($this->season)->create(['jolpica_constructor_id' => $constructor]);
        }

        foreach ([
            ['norris', 'Lando', 'Norris', 'NOR'],
            ['zhou', 'Guanyu', 'Zhou', 'ZHO'],
            ['hulkenberg', 'Nico', 'Hülkenberg', 'HUL'],
            ['hadjar', 'Isack', 'Hadjar', 'HAD'],
            ['doohan', 'Jack', 'Doohan', 'DOO'],
            ['crawford', 'Jim', 'Crawford', null],
        ] as [$id, $given, $family, $code]) {
            Driver::factory()->create(['jolpica_driver_id' => $id, 'given_name' => $given, 'family_name' => $family, 'code' => $code]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $fixture, string $acronym): array
    {
        return collect($this->openF1Fixture($fixture))->firstWhere('name_acronym', $acronym);
    }

    private function match(string $acronym, string $fixture = 'drivers_abu_dhabi_2024_fp1'): DriverMatch
    {
        return app(DriverMatcher::class)->match($this->practice->load('round.season'), $this->row($fixture, $acronym));
    }

    public function test_matches_rookies_by_acronym_and_name_whatever_number_they_use(): void
    {
        $doohan = $this->match('DOO');
        $hadjar = $this->match('HAD');

        $this->assertSame(OpenF1MatchStatus::Matched, $doohan->status);
        $this->assertSame('doohan', $doohan->driver->jolpica_driver_id);
        $this->assertSame(61, $doohan->entry->car_number);
        $this->assertSame('alpine', $doohan->seasonTeam->jolpica_constructor_id);
        $this->assertSame('hadjar', $hadjar->driver->jolpica_driver_id);
        $this->assertSame('red_bull', $hadjar->seasonTeam->jolpica_constructor_id);
    }

    public function test_ignores_accents_and_maps_the_team_name_of_the_season(): void
    {
        $this->assertSame('hulkenberg', $this->match('HUL')->driver->jolpica_driver_id);
        $this->assertSame('sauber', $this->match('ZHO')->seasonTeam->jolpica_constructor_id);
    }

    public function test_leaves_a_stand_in_unknown_to_jolpica_unmatched(): void
    {
        $match = $this->match('HIR');

        $this->assertSame(OpenF1MatchStatus::Unmatched, $match->status);
        $this->assertNull($match->entry);
        $this->assertStringContainsString('Ryo HIRAKAWA', $match->note);
    }

    public function test_never_matches_on_surname_alone(): void
    {
        $match = $this->match('CRA', 'drivers_crawford_2025_fp1');

        $this->assertSame(OpenF1MatchStatus::Unmatched, $match->status);
        $this->assertNull($match->driver);
    }

    public function test_reports_a_conflict_when_jolpica_has_someone_else_in_that_car_number_this_round(): void
    {
        $race = RaceSession::factory()->for($this->practice->round)->create(['type' => SessionType::Race]);
        DriverEntry::factory()->for($race)->for(Driver::factory()->state(['jolpica_driver_id' => 'other', 'code' => 'OTH']))->create(['car_number' => 4]);

        $match = $this->match('NOR');

        $this->assertSame(OpenF1MatchStatus::Conflict, $match->status);
        $this->assertStringContainsString('Car 4 is other', $match->note);
    }

    public function test_uses_a_configured_override(): void
    {
        Driver::factory()->create(['jolpica_driver_id' => 'hirakawa', 'given_name' => 'Ryo', 'family_name' => 'Hirakawa']);
        config(['openf1.driver_overrides' => ['2024:28' => 'hirakawa']]);

        $this->assertSame('hirakawa', $this->match('HIR')->driver->jolpica_driver_id);
    }

    public function test_links_race_cars_to_jolpica_entries_by_number_in_the_same_round(): void
    {
        $race = $this->austinRace();

        $verstappen = OpenF1SessionDriver::where('race_session_id', $race->id)->where('driver_number', 3)->sole();

        $this->assertSame(OpenF1MatchStatus::Matched, $verstappen->match_status);
        $this->assertSame('max_verstappen', $verstappen->driver->jolpica_driver_id);
        $this->assertSame($race->id, $verstappen->driverEntry->race_session_id);
        $this->assertSame(0, OpenF1SessionDriver::where('match_status', '!=', OpenF1MatchStatus::Matched)->count());
        $this->assertSame(22, DriverEntry::where('race_session_id', $race->id)->count());
    }

    public function test_creates_practice_entries_and_keeps_unknown_cars(): void
    {
        $this->austinRace();
        $this->normalizeOpenF1(DriversNormalizer::class, 'drivers_11389');

        $practice = RaceSession::firstWhere('openf1_session_key', 11389);
        $herta = OpenF1SessionDriver::where('race_session_id', $practice->id)->where('driver_number', 25)->sole();

        $this->assertSame(21, DriverEntry::where('race_session_id', $practice->id)->count());
        $this->assertSame(OpenF1MatchStatus::Unmatched, $herta->match_status);
        $this->assertSame('Colton HERTA', $herta->full_name);
        $this->assertSame('Cadillac', $herta->team_name);
    }
}
