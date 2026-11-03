<?php

namespace Tests\Feature\Admin;

use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Filament\Resources\AchievementAwards\AchievementAwardResource;
use App\Filament\Resources\Rounds\Pages\ListRounds;
use App\Filament\Resources\Rounds\Pages\ViewRound;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\Season;
use App\Models\SessionLap;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class RoundsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        Filament::setCurrentPanel('nimda');
        Date::setTestNow('2024-11-03 12:00:00');
        $this->catalogue();
    }

    public function test_lists_the_current_season_newest_round_first_with_what_is_in(): void
    {
        $older = $this->round(2024, 3, ['name' => 'Older GP', 'date' => '2024-03-03']);
        $newer = $this->round(2024, 4, ['name' => 'Newer GP', 'date' => '2024-04-04']);
        $lastYear = $this->round(2023, 9, ['name' => 'Last Year GP']);
        $race = $this->raceSession(SessionType::Race, $newer);
        $this->resultFor($this->car($race, 1));
        $this->message($race, 'GREEN LIGHT');

        Livewire::test(ListRounds::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->assertCanNotSeeTableRecords([$lastYear])
            ->assertTableColumnStateSet('has_race_results', true, $newer)
            ->assertTableColumnStateSet('has_qualifying', false, $newer)
            ->assertTableColumnStateSet('has_openf1_data', true, $newer)
            ->assertTableColumnStateSet('has_race_results', false, $older)
            ->assertTableColumnStateSet('has_openf1_data', false, $older)
            ->filterTable('season', Season::firstWhere('year', 2023)->id)
            ->assertCanSeeTableRecords([$lastYear])
            ->assertCanNotSeeTableRecords([$newer, $older]);
    }

    public function test_a_round_page_shows_each_session_and_links_its_awards(): void
    {
        $round = $this->round(2024, 5, ['name' => 'Mexico City Grand Prix']);
        $race = $this->raceSession(SessionType::Race, $round);
        $qualifying = $this->raceSession(SessionType::Qualifying, $round);
        $practice = $this->raceSession(SessionType::Practice1, $round);

        $winner = $this->car($race, 12);
        $retired = $this->car($race, 77);
        $this->resultFor($winner, ['position' => 1, 'position_text' => '1', 'grid' => 2, 'status' => 'Finished', 'time_text' => '1:35:01.234', 'points' => 25, 'fastest_lap_rank' => 1, 'fastest_lap_time_millis' => 78_901]);
        $this->resultFor($retired, ['position' => null, 'position_text' => 'R', 'grid' => 5, 'status' => 'Accident', 'is_classified' => false, 'classification_order' => 20]);
        $this->resultFor($this->car($race, 3), ['position' => null, 'position_text' => 'D', 'status' => 'Disqualified', 'is_classified' => false, 'classification_order' => 21]);
        $this->resultFor($this->car($race, 4), ['position' => null, 'position_text' => 'W', 'status' => 'Did not start', 'is_classified' => false, 'classification_order' => 22]);
        $this->message($race, 'CAR 77 (BOT) STOPPED AT TURN 7');

        $this->qualifying($this->car($qualifying, 12), 1, 80_100, 79_500, 78_321);
        $driver = $this->car($practice, 77);
        SessionLap::factory()->create(['race_session_id' => $practice->id, 'driver_number' => 77, 'driver_entry_id' => $driver->id, 'lap_number' => 1, 'duration_millis' => 81_000]);
        SessionLap::factory()->create(['race_session_id' => $practice->id, 'driver_number' => 77, 'driver_entry_id' => $driver->id, 'lap_number' => 2, 'duration_millis' => 80_250]);

        $award = AchievementAward::factory()->create([
            'race_session_id' => $race->id,
            'driver_entry_id' => $retired->id,
            'driver_id' => $retired->driver_id,
            'achievement_id' => Achievement::firstWhere('key', 'home_before_dinner')->id,
            'status' => AwardStatus::Pending,
        ]);

        Livewire::test(ViewRound::class, ['record' => $round->getRouteKey()])
            ->assertSee('Mexico City Grand Prix')
            ->assertSeeInOrder(['FP1', 'Qualifying', 'Race', 'Awards'])
            ->assertSeeText('1:35:01.234')
            ->assertSeeText('1:18.901 (fastest)')
            ->assertSeeText('Accident')
            ->assertSeeText('DSQ · Disqualified')
            ->assertSeeText('DNS · Did not start')
            ->assertSeeText('1:18.321')
            ->assertSeeText('1:20.250')
            ->assertSeeText('CAR 77 (BOT) STOPPED AT TURN 7')
            ->assertSeeHtml(AchievementAwardResource::getUrl('view', ['record' => $award]));
    }
}
