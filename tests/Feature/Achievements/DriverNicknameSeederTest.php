<?php

namespace Tests\Feature\Achievements;

use App\Enums\NicknameStatus;
use App\Models\Driver;
use App\Models\DriverNickname;
use Database\Seeders\DriverNicknameSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DriverNicknameSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_seeds_drafts_with_planned_evolutions_and_skips_drivers_not_imported_yet(): void
    {
        $verstappen = Driver::factory()->create(['jolpica_driver_id' => 'max_verstappen']);
        $bortoleto = Driver::factory()->create(['jolpica_driver_id' => 'bortoleto']);
        // Matched on the name when the Jolpica ID differs.
        $schumacher = Driver::factory()->create(['jolpica_driver_id' => 'm_schumacher', 'given_name' => 'Michael', 'family_name' => 'Schumacher']);

        $seeder = $this->app->make(DriverNicknameSeeder::class);
        $seeder->run();

        $this->assertSame(['MaxSettings'], $verstappen->nicknames->pluck('nickname')->all());
        $this->assertSame('Everything on max', $verstappen->nicknameOn()->motto);
        $this->assertSame('SchuTime', $schumacher->nicknameOn()->nickname);

        [$gabbles, $gabblefly] = $bortoleto->nicknames->all();
        $this->assertSame(['Gabbles', NicknameStatus::Active, 1], [$gabbles->nickname, $gabbles->status, $gabbles->stage]);
        $this->assertSame(['Gabblefly', NicknameStatus::Planned, 2, $gabbles->id], [$gabblefly->nickname, $gabblefly->status, $gabblefly->stage, $gabblefly->evolves_from_id]);

        $this->assertTrue(DriverNickname::query()->get()->every(fn (DriverNickname $nickname) => $nickname->is_draft));
        $this->assertContains('Rafael Câmara (RafaCut)', $seeder->skipped);
        $this->assertCount(4, DriverNickname::query()->get());
    }

    public function test_it_never_overwrites_what_the_cms_changed(): void
    {
        $lawson = Driver::factory()->create(['jolpica_driver_id' => 'lawson']);
        $this->seed(DriverNicknameSeeder::class);
        $lawson->nicknames()->where('stage', 1)->first()->update(['nickname' => 'OutLawson', 'motto' => 'Holds the line', 'is_draft' => false]);
        $lawson->nicknames()->where('stage', 2)->first()->update(['nickname' => 'LawAndOrder']);

        $this->seed(DriverNicknameSeeder::class);

        $this->assertSame(
            [['OutLawson', 'Holds the line', false], ['LawAndOrder', 'Stands his ground', true]],
            $lawson->fresh()->nicknames->map(fn (DriverNickname $nickname) => [$nickname->nickname, $nickname->motto, $nickname->is_draft])->all(),
        );
    }
}
