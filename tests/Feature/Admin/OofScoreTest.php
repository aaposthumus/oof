<?php

namespace Tests\Feature\Admin;

use App\Achievements\OofScoreStandings;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Filament\Resources\AchievementAwards\Pages\ViewAchievementAward;
use App\Filament\Resources\OofscoreStandings\Pages\ListOofscoreStandings;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\Driver;
use App\Models\OofscoreStanding;
use App\Models\RaceSession;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class OofScoreTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private RaceSession $race;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com'], 'achievements.first_season' => 2024]);
        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        Filament::setCurrentPanel('nimda');
        $this->catalogue();

        $this->race = $this->raceSession(SessionType::Race, $this->round(2024, 1));
        $this->race->update(['achievements_checked_at' => now()]);
        $this->driver = Driver::factory()->create(['given_name' => 'Ada', 'family_name' => 'Example']);
    }

    private function award(string $key, AwardStatus $status = AwardStatus::Final): AchievementAward
    {
        $entry = $this->race->driverEntries()->firstWhere('driver_id', $this->driver->id) ?? $this->car($this->race, 7, driver: $this->driver);

        return AchievementAward::factory()->create([
            'race_session_id' => $this->race->id,
            'achievement_id' => Achievement::firstWhere('key', $key)->id,
            'driver_entry_id' => $entry->id,
            'driver_id' => $this->driver->id,
            'status' => $status,
        ]);
    }

    public function test_the_leaderboard_shows_the_latest_round(): void
    {
        $this->award('good_boy');
        app(OofScoreStandings::class)->rebuild($this->race->round->season);

        Livewire::test(ListOofscoreStandings::class)
            ->assertCanSeeTableRecords(OofscoreStanding::all())
            ->assertSee('Ada Example')
            ->assertSee('2024 R1');
    }

    public function test_confirming_an_award_updates_the_oofscore(): void
    {
        $award = $this->award('good_boy', AwardStatus::Pending);
        $this->award('lapped_dog');
        app(OofScoreStandings::class)->rebuild($this->race->round->season);
        $this->assertSame(10, OofscoreStanding::query()->sole()->points);

        Livewire::test(ViewAchievementAward::class, ['record' => $award->getRouteKey()])->callAction('confirm');

        $this->assertSame(35, OofscoreStanding::query()->sole()->points);
    }

    public function test_closing_a_season_with_awards_waiting_is_refused(): void
    {
        $this->award('good_boy', AwardStatus::Pending);

        Livewire::test(ListOofscoreStandings::class)
            ->callAction('close', ['season_id' => $this->race->round->season_id])
            ->assertNotified('Not closed');

        $this->assertFalse($this->race->round->season->fresh()->isClosed());
    }
}
