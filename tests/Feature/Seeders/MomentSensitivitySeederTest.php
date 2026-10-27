<?php

namespace Tests\Feature\Seeders;

use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use App\Models\Driver;
use App\Models\MomentSensitivity;
use App\Models\Round;
use App\Models\Season;
use Database\Seeders\MomentSensitivitySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MomentSensitivitySeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createRoundsAndDrivers(): void
    {
        $keys = [...array_keys(MomentSensitivitySeeder::WEEKENDS), ...array_column(MomentSensitivitySeeder::INJURIES, 0)];

        foreach (array_unique($keys) as $key) {
            [$year, $name] = explode('|', $key);
            Round::factory()->for(Season::firstOrCreate(['year' => (int) $year]))->create(['name' => $name, 'round_number' => Round::count() + 1]);
        }

        foreach (array_filter(array_unique(array_column(MomentSensitivitySeeder::INJURIES, 1))) as $jolpicaId) {
            Driver::factory()->create(['jolpica_driver_id' => $jolpicaId]);
        }
    }

    public function test_seeds_fatal_entries_for_the_round_and_injuries_for_the_driver_involved(): void
    {
        $this->createRoundsAndDrivers();

        $this->seed(MomentSensitivitySeeder::class);

        $fatal = MomentSensitivity::query()->where('severity', SensitivitySeverity::Fatal)->get();
        $injuries = MomentSensitivity::query()->where('severity', SensitivitySeverity::Injury)->get();
        $this->assertCount(count(MomentSensitivitySeeder::WEEKENDS), $fatal);
        $this->assertNull($fatal->pluck('driver_id')->filter()->first());
        $this->assertCount(count(MomentSensitivitySeeder::INJURIES), $injuries);
        $this->assertSame([SensitivityLevel::Sensitive], MomentSensitivity::pluck('level')->unique()->values()->all());

        $lauda = $injuries->first(fn (MomentSensitivity $sensitivity) => $sensitivity->driver?->jolpica_driver_id === 'lauda');
        $this->assertSame('1976 German Grand Prix', $lauda->round->season->year.' '.$lauda->round->name);
        $this->assertSame(MomentSensitivitySeeder::WEEKENDS['1959|German Grand Prix'], MomentSensitivity::whereRelation('round', 'name', 'German Grand Prix')->whereRelation('round.season', 'year', 1959)->sole()->note);
        $this->assertNull($injuries->first(fn (MomentSensitivity $sensitivity) => str_contains($sensitivity->note, 'Cameraman'))->driver_id);
        $this->assertNotNull(MomentSensitivity::where('note', 'like', 'Patrick Depailler%')->value('id'));
    }

    public function test_rerunning_keeps_a_level_changed_by_hand_and_cleans_up_legacy_injury_rows(): void
    {
        $this->createRoundsAndDrivers();
        $injuryOnly = Round::whereRelation('season', 'year', 2013)->where('name', 'German Grand Prix')->sole();
        $both = Round::whereRelation('season', 'year', 1994)->where('name', 'San Marino Grand Prix')->sole();
        $legacy = MomentSensitivity::factory()->for($injuryOnly)->create(['note' => 'Old injury note.']);
        $merged = MomentSensitivity::factory()->for($both)->create(['note' => 'Fatal note. Injury note.']);
        $changedByHand = MomentSensitivity::factory()->blocked()->for(Round::whereRelation('season', 'year', 2020)->where('name', 'Bahrain Grand Prix')->sole())->create();

        $this->seed(MomentSensitivitySeeder::class);
        $changedByHand->update(['note' => 'Kept by hand.']);
        $this->seed(MomentSensitivitySeeder::class);

        $this->assertModelMissing($legacy);
        $this->assertSame(MomentSensitivitySeeder::WEEKENDS['1994|San Marino Grand Prix'], $merged->refresh()->note);
        $this->assertSame(SensitivityLevel::Blocked, $changedByHand->refresh()->level);
        $this->assertSame(
            count(MomentSensitivitySeeder::WEEKENDS) + count(MomentSensitivitySeeder::INJURIES) + 1,
            MomentSensitivity::count(),
        );
    }
}
