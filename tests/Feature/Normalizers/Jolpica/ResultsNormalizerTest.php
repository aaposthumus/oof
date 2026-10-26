<?php

namespace Tests\Feature\Normalizers\Jolpica;

use App\Enums\SessionType;
use App\Models\DriverEntry;
use App\Models\Result;
use App\Models\SeasonTeam;
use App\Normalizers\Jolpica\ResultsNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesJolpicaFixtures;
use Tests\TestCase;

class ResultsNormalizerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesJolpicaFixtures;

    public function test_creates_round_session_entry_and_race_result(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2024_page_0');

        $winner = Result::with('driverEntry.driver', 'driverEntry.raceSession.round')->where('position', 1)->sole();

        $this->assertSame('max_verstappen', $winner->driverEntry->driver->jolpica_driver_id);
        $this->assertSame(1, $winner->driverEntry->car_number);
        $this->assertSame(SessionType::Race, $winner->driverEntry->raceSession->type);
        $this->assertSame('Chinese Grand Prix', $winner->driverEntry->raceSession->round->name);
        $this->assertSame('25.00', $winner->points);
        $this->assertSame(1, $winner->grid);
        $this->assertSame(56, $winner->laps_completed);
        $this->assertTrue($winner->is_classified);
        $this->assertSame('Finished', $winner->status);
        $this->assertSame(6_052_554, $winner->time_millis);
        $this->assertSame(98_406, $winner->fastest_lap_time_millis);
    }

    public function test_merges_race_split_across_pages_in_source_order(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2024_page_0', 'results_2024_page_100');

        $drivers = Result::orderBy('classification_order')->with('driverEntry.driver')->get()
            ->map(fn (Result $result) => $result->driverEntry->driver->jolpica_driver_id)->all();

        $this->assertSame(['max_verstappen', 'norris', 'perez'], $drivers);
        $this->assertSame(1, DriverEntry::distinct()->count('race_session_id'));
    }

    public function test_normalizing_twice_keeps_the_same_rows(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2024_page_0');
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2024_page_0');

        $this->assertSame(2, DriverEntry::count());
        $this->assertSame(2, Result::count());
    }

    public function test_shared_drive_creates_an_entry_per_car(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_1955_1');

        $farina = DriverEntry::whereRelation('driver', 'jolpica_driver_id', 'farina')->with('result')->orderBy('car_number')->get();

        $this->assertSame([10, 12], $farina->pluck('car_number')->all());
        $this->assertSame(['1.33', '2.00'], $farina->pluck('result.points')->all());
    }

    public function test_assigns_renamed_constructor_to_its_lasting_team(): void
    {
        $this->normalizeFixtures(ResultsNormalizer::class, 'results_2019_13');

        $seasonTeam = SeasonTeam::with('team')->where('jolpica_constructor_id', 'toro_rosso')->sole();

        $this->assertSame('racing_bulls', $seasonTeam->team->slug);
        $this->assertSame('Toro Rosso', $seasonTeam->name);
    }
}
