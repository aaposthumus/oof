<?php

namespace Tests\Feature\Normalizers;

use App\Enums\DriverRole;
use App\Models\Season;
use App\Models\SeasonDriver;
use App\Normalizers\Jolpica\ResultsNormalizer;
use App\Normalizers\SeasonRosterBuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class SeasonRosterBuilderTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_mid_season_team_switch_gives_a_roster_row_per_team(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2019_12', 'results_2019_13');

        app(SeasonRosterBuilder::class)->rebuild(Season::where('year', 2019)->sole());

        $rows = SeasonDriver::with('seasonTeam')->orderBy('first_round_number')->get();

        $this->assertSame(['red_bull', 'toro_rosso'], $rows->pluck('seasonTeam.jolpica_constructor_id')->all());
        $this->assertSame([[12, 12], [13, 13]], $rows->map(fn (SeasonDriver $row) => [$row->first_round_number, $row->last_round_number])->all());
        $this->assertSame([10, 10], $rows->pluck('usual_number')->all());
        $this->assertSame([DriverRole::RaceDriver, DriverRole::RaceDriver], $rows->pluck('role')->all());
    }

    public function test_rebuilding_keeps_a_role_set_by_hand(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2019_12');
        $season = Season::where('year', 2019)->sole();
        app(SeasonRosterBuilder::class)->rebuild($season);
        SeasonDriver::query()->update(['role' => DriverRole::Reserve]);

        app(SeasonRosterBuilder::class)->rebuild($season);

        $this->assertSame(DriverRole::Reserve, SeasonDriver::sole()->role);
    }
}
