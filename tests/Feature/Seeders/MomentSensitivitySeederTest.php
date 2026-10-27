<?php

namespace Tests\Feature\Seeders;

use App\Enums\SensitivityLevel;
use App\Models\MomentSensitivity;
use App\Models\Round;
use App\Models\Season;
use Database\Seeders\MomentSensitivitySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MomentSensitivitySeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeds_both_lists_merges_a_weekend_in_both_and_keeps_a_level_changed_by_hand(): void
    {
        foreach (array_keys([...MomentSensitivitySeeder::WEEKENDS, ...MomentSensitivitySeeder::INJURY_WEEKENDS]) as $key) {
            [$year, $name] = explode('|', $key);
            Round::factory()->for(Season::firstOrCreate(['year' => (int) $year]))->create(['name' => $name, 'round_number' => Round::count() + 1]);
        }

        $this->seed(MomentSensitivitySeeder::class);

        $merged = MomentSensitivity::query()->whereRelation('round', 'name', 'San Marino Grand Prix')->whereRelation('round.season', 'year', 1994)->sole();
        $this->assertSame(MomentSensitivitySeeder::WEEKENDS['1994|San Marino Grand Prix'].' '.MomentSensitivitySeeder::INJURY_WEEKENDS['1994|San Marino Grand Prix'], $merged->note);
        $this->assertDatabaseCount('moment_sensitivities', count([...MomentSensitivitySeeder::WEEKENDS, ...MomentSensitivitySeeder::INJURY_WEEKENDS]));
        $this->assertSame([SensitivityLevel::Sensitive], MomentSensitivity::pluck('level')->unique()->values()->all());

        $merged->update(['level' => SensitivityLevel::Blocked]);
        $this->seed(MomentSensitivitySeeder::class);

        $this->assertSame(SensitivityLevel::Blocked, $merged->refresh()->level);
        $this->assertDatabaseCount('moment_sensitivities', count([...MomentSensitivitySeeder::WEEKENDS, ...MomentSensitivitySeeder::INJURY_WEEKENDS]));
    }
}
