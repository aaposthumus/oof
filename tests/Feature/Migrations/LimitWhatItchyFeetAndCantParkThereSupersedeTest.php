<?php

namespace Tests\Feature\Migrations;

use App\Enums\AchievementShelf;
use App\Models\Achievement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LimitWhatItchyFeetAndCantParkThereSupersedeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_22_183835_limit_what_itchy_feet_and_cant_park_there_supersede.php');
    }

    public function test_the_heavier_penalties_leave_their_supersedes_and_the_rest_stays(): void
    {
        $all = ['it_was_like_that_when_i_got_there', 'sibling_rivalry', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'];
        $itchyFeet = Achievement::factory()->create(['key' => 'itchy_feet', 'shelf' => AchievementShelf::Oof, 'supersedes' => $all]);
        $cantParkThere = Achievement::factory()->create(['key' => 'cant_park_there', 'shelf' => AchievementShelf::Oof, 'supersedes' => $all]);
        $bumperCars = Achievement::factory()->create(['key' => 'bumper_cars', 'shelf' => AchievementShelf::Oof, 'supersedes' => $all]);

        $this->migration()->up();

        $this->assertSame(['it_was_like_that_when_i_got_there', 'sibling_rivalry', 'high_five', 'perfect_ten'], $itchyFeet->fresh()->supersedes);
        $this->assertSame(['it_was_like_that_when_i_got_there', 'sibling_rivalry', 'high_five', 'perfect_ten'], $cantParkThere->fresh()->supersedes);
        $this->assertSame($all, $bumperCars->fresh()->supersedes);

        $this->migration()->up();
        $this->assertSame(['it_was_like_that_when_i_got_there', 'sibling_rivalry', 'high_five', 'perfect_ten'], $itchyFeet->fresh()->supersedes);
    }

    public function test_rolling_back_puts_them_back_and_a_missing_row_is_fine(): void
    {
        $itchyFeet = Achievement::factory()->create(['key' => 'itchy_feet', 'shelf' => AchievementShelf::Oof, 'supersedes' => ['high_five']]);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(['high_five', 'drive_thru', 'red_light_green_light'], $itchyFeet->fresh()->supersedes);
    }
}
