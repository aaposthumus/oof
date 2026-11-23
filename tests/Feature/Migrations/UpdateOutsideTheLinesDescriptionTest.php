<?php

namespace Tests\Feature\Migrations;

use App\Models\Achievement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UpdateOutsideTheLinesDescriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_23_212725_update_outside_the_lines_description.php');
    }

    public function test_the_description_asks_for_two_deleted_lap_times(): void
    {
        $achievement = Achievement::factory()->create(['key' => 'outside_the_lines', 'description' => 'Lap time deleted for track limits.']);

        $this->migration()->up();

        $this->assertSame('2 or more lap times deleted for track limits in one race or sprint.', $achievement->refresh()->description);

        $this->migration()->down();

        $this->assertSame('Lap time deleted for track limits.', $achievement->refresh()->description);
    }

    public function test_keeps_a_description_edited_in_the_cms(): void
    {
        $achievement = Achievement::factory()->create(['key' => 'outside_the_lines', 'description' => 'Coloured over the edge.']);

        $this->migration()->up();

        $this->assertSame('Coloured over the edge.', $achievement->refresh()->description);
    }
}
