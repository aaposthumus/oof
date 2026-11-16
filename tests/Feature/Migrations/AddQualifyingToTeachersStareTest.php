<?php

namespace Tests\Feature\Migrations;

use App\Models\Achievement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AddQualifyingToTeachersStareTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_16_195214_add_qualifying_to_teachers_stare.php');
    }

    public function test_adds_qualifying_to_the_sessions_and_the_description(): void
    {
        $stare = Achievement::factory()->create([
            'key' => 'teachers_stare',
            'session_types' => ['race', 'sprint'],
            'description' => 'Black-and-white warning flag, for any reason (track limits, driving slowly, etc.).',
        ]);

        $this->migration()->up();

        $this->assertSame(['race', 'sprint', 'qualifying', 'sprint_qualifying'], $stare->refresh()->session_types);
        $this->assertSame('Black-and-white warning flag in a race or qualifying, for any reason (track limits, driving slowly, etc.).', $stare->description);

        $this->migration()->down();

        $this->assertSame(['race', 'sprint'], $stare->refresh()->session_types);
        $this->assertSame('Black-and-white warning flag, for any reason (track limits, driving slowly, etc.).', $stare->description);
    }

    public function test_keeps_sessions_and_a_description_edited_in_the_cms(): void
    {
        $stare = Achievement::factory()->create(['key' => 'teachers_stare', 'session_types' => ['race'], 'description' => 'A stern look.']);

        $this->migration()->up();

        $this->assertSame(['race'], $stare->refresh()->session_types);
        $this->assertSame('A stern look.', $stare->description);
    }
}
