<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Catalogue;
use App\Achievements\CatalogueSync;
use App\Enums\SessionType;
use App\Models\Circuit;
use App\Models\Driver;
use App\Models\NationalityCountry;
use App\Models\RaceSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The nationality-to-country table and the at_home predicate (Phase 12b),
 * for Hometown Hero: the circuit's country decides, not the Grand Prix's
 * name. Proven with an achievement that exists only in this test.
 */
class HomeCountriesTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        config(['achievement_catalogue.achievements.home_win' => [
            ...config('achievement_catalogue.achievements.did_the_thing'),
            'name' => 'Home Win',
            'sessions' => ['race'],
            'trigger' => ['type' => 'match', 'fact' => 'result', 'where' => [['position', '=', 1], 'at_home']],
        ]]);
        app(CatalogueSync::class)->apply();
    }

    private function win(string $nationality, string $country, string $grandPrix = 'A Grand Prix'): RaceSession
    {
        $circuit = Circuit::factory()->create(['country' => $country]);
        $race = $this->raceSession(SessionType::Race, $this->round(2024, Circuit::query()->count(), ['circuit_id' => $circuit->id, 'name' => $grandPrix]));
        $this->resultFor($this->car($race, 1, null, Driver::factory()->create(['nationality' => $nationality])), ['position' => 1, 'position_text' => '1', 'status' => 'Finished']);

        return $race;
    }

    public function test_the_table_is_seeded_with_every_nationality_in_the_data(): void
    {
        $countries = NationalityCountry::homeCountries();

        $this->assertCount(40, $countries);
        $this->assertSame(['UK', 'USA', 'Monaco', 'Germany'], [$countries['British'], $countries['American'], $countries['Monegasque'], $countries['East German']]);
    }

    public function test_the_circuits_country_decides_not_the_grand_prixs_name(): void
    {
        $this->assertCount(1, $this->detect('home_win', $this->win('Italian', 'Italy', 'San Marino Grand Prix')));
        $this->assertCount(1, $this->detect('home_win', $this->win('American', 'USA', 'Las Vegas Grand Prix')));
        $this->assertCount(0, $this->detect('home_win', $this->win('British', 'Italy', 'Italian Grand Prix')));
        $this->assertCount(0, $this->detect('home_win', $this->win('Swiss', 'France', 'Swiss Grand Prix')));
        $this->assertCount(0, $this->detect('home_win', $this->win('Atlantean', 'UK')));
    }

    public function test_an_edit_of_the_table_changes_the_catalogue_version(): void
    {
        $before = app(Catalogue::class)->version();

        NationalityCountry::query()->where('nationality', 'Rhodesian')->update(['country' => 'Zimbabwe']);

        $this->assertNotSame($before, app(Catalogue::class)->version());
    }
}
