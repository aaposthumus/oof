<?php

namespace Tests\Feature\Admin;

use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Filament\Resources\AchievementAwards\Pages\ListAchievementAwards;
use App\Filament\Resources\AchievementAwards\Pages\ViewAchievementAward;
use App\Filament\Resources\AchievementAwards\Tables\AchievementAwardsTable;
use App\Filament\Resources\Achievements\AchievementResource;
use App\Filament\Resources\Achievements\Pages\EditAchievement;
use App\Filament\Resources\Achievements\Pages\ListAchievements;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\Season;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class AchievementAwardsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com']]);

        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('nimda');
        $this->catalogue();
    }

    private function award(string $key = 'smile_for_the_camera', AwardStatus $status = AwardStatus::Final): AchievementAward
    {
        $race = RaceSession::first() ?? $this->raceSession();
        $car = $this->car($race, AchievementAward::count() + 1);
        $message = $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');

        return AchievementAward::factory()->create([
            'race_session_id' => $race->id,
            'driver_entry_id' => $car->id,
            'driver_id' => $car->driver_id,
            'achievement_id' => Achievement::firstWhere('key', $key)->id,
            'status' => $status,
            'evidence' => ['source' => 'race_control', 'messages' => [['id' => $message->id, 'issued_at' => '2024-05-05 20:01:00', 'lap' => 12, 'message' => $message->message]]],
        ]);
    }

    public function test_lists_awards_per_session_and_shows_the_evidence_word_for_word(): void
    {
        $award = $this->award();

        Livewire::test(ListAchievementAwards::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$award])
            ->assertSee('2024 R5 Miami Grand Prix · Race');

        Livewire::test(ViewAchievementAward::class, ['record' => $award->getRouteKey()])
            ->assertSee('FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');
    }

    public function test_filters_on_achievement_status_and_session(): void
    {
        $award = $this->award();
        $other = $this->award('high_five', AwardStatus::Pending);

        Livewire::test(ListAchievementAwards::class)
            ->set('activeTab', 'all')
            ->filterTable('achievement_id', $award->achievement_id)
            ->assertCanSeeTableRecords([$award])
            ->assertCanNotSeeTableRecords([$other])
            ->resetTableFilters()
            ->filterTable('status', AwardStatus::Pending->value)
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$award])
            ->resetTableFilters()
            ->filterTable('scope', ['season' => Season::firstWhere('year', 2024)->id, 'session' => SessionType::Qualifying->value])
            ->assertCanNotSeeTableRecords([$award, $other]);
    }

    public function test_season_round_session_and_driver_are_dropdowns_that_narrow_each_other(): void
    {
        $award = $this->award();
        $race = $award->raceSession;
        $qualifying = $this->raceSession(SessionType::Qualifying, $race->round);
        $laterRound = $this->round(2024, 19, ['name' => 'Mexico City Grand Prix']);
        $oldSeasonAward = AchievementAward::factory()->create([
            'race_session_id' => $this->raceSession(SessionType::Race, $this->round(2023, 3))->id,
            'achievement_id' => $award->achievement_id,
        ]);
        $season = Season::firstWhere('year', 2024);

        $component = Livewire::test(ListAchievementAwards::class)->set('activeTab', 'all');

        // The current (here: latest) season is the default.
        $component->assertCanSeeTableRecords([$award])->assertCanNotSeeTableRecords([$oldSeasonAward]);

        $component
            ->set('tableFilters.scope.season', Season::firstWhere('year', 2023)->id)
            ->assertCanSeeTableRecords([$oldSeasonAward])
            ->assertCanNotSeeTableRecords([$award])
            ->set('tableFilters.scope.season', $season->id)
            ->set('tableFilters.scope.round', $laterRound->id)
            ->assertCanNotSeeTableRecords([$award])
            ->set('tableFilters.scope.round', $race->round_id)
            ->assertCanSeeTableRecords([$award])
            ->set('tableFilters.scope.session', SessionType::Qualifying->value)
            ->assertCanNotSeeTableRecords([$award])
            ->set('tableFilters.scope.session', SessionType::Race->value)
            ->assertCanSeeTableRecords([$award]);

        // A round left over from another season does not hide the new season's awards.
        $component->set('tableFilters.scope.session', null)
            ->set('tableFilters.scope.season', Season::firstWhere('year', 2023)->id)
            ->assertCanSeeTableRecords([$oldSeasonAward]);

        $this->assertSame([$laterRound->id => '19 · Mexico City Grand Prix', $race->round_id => '5 · Miami Grand Prix'], AchievementAwardsTable::roundsOf($season->id));
        $this->assertNotNull($qualifying);
    }

    public function test_the_driver_dropdown_only_lists_drivers_who_raced_in_the_season(): void
    {
        $racer = $this->award()->driver;
        $other = $this->car($this->raceSession(SessionType::Race, $this->round(2023, 3)), 7)->driver;

        $this->assertSame([$racer->id], array_keys(AchievementAwardsTable::driversWhoRaced(Season::firstWhere('year', 2024)->id)));
        $this->assertSame([$other->id], array_keys(AchievementAwardsTable::driversWhoRaced(Season::firstWhere('year', 2023)->id)));
        $this->assertCount(2, AchievementAwardsTable::driversWhoRaced(null));
    }

    public function test_suppressing_needs_a_reason_and_records_who_did_it(): void
    {
        $award = $this->award();

        Livewire::test(ListAchievementAwards::class)
            ->set('activeTab', 'all')
            ->callTableAction('suppress', $award, ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);
        $this->assertSame(AwardStatus::Final, $award->fresh()->status);

        Livewire::test(ListAchievementAwards::class)
            ->set('activeTab', 'all')
            ->callTableAction('suppress', $award, ['reason' => 'He was ill that weekend.']);

        $award->refresh();
        $this->assertSame(AwardStatus::Suppressed, $award->status);
        $this->assertSame('He was ill that weekend.', $award->decision_note);
        $this->assertSame($this->admin->id, $award->decided_by);
    }

    public function test_confirming_a_cause_without_a_car_asks_for_the_car(): void
    {
        $race = $this->raceSession(SessionType::Practice1);
        $car = $this->car($race, 16);
        $award = AchievementAward::factory()->pending()->create([
            'race_session_id' => $race->id,
            'achievement_id' => Achievement::firstWhere('key', 'whole_class_detention')->id,
        ]);

        Livewire::test(ViewAchievementAward::class, ['record' => $award->getRouteKey()])
            ->callAction('confirm', ['driver_entry_id' => null])
            ->assertHasActionErrors(['driver_entry_id' => 'required']);
        $this->assertSame(AwardStatus::Pending, $award->fresh()->status);

        Livewire::test(ViewAchievementAward::class, ['record' => $award->getRouteKey()])
            ->callAction('confirm', ['driver_entry_id' => $car->id]);

        $award->refresh();
        $this->assertSame(AwardStatus::Final, $award->status);
        $this->assertSame($car->driver_id, $award->driver_id);
    }

    public function test_handing_back_clears_the_decision(): void
    {
        $award = $this->award();
        $award->suppress($this->admin, 'Not funny.');

        Livewire::test(ViewAchievementAward::class, ['record' => $award->getRouteKey()])
            ->assertActionHidden('suppress')
            ->callAction('restore');

        $this->assertFalse($award->fresh()->isDecided());
    }

    public function test_a_historic_suppression_can_be_handed_back(): void
    {
        $award = $this->award();
        $award->update(['status' => AwardStatus::Suppressed, 'decided_at' => now(), 'decision_note' => AchievementAward::HISTORIC_NOTE]);

        Livewire::test(ViewAchievementAward::class, ['record' => $award->getRouteKey()])
            ->assertSee('by the engine')
            ->assertActionVisible('restore')
            ->callAction('restore');

        $award->refresh();
        $this->assertSame(AwardStatus::Pending, $award->status);
        $this->assertNotNull($award->handed_back_at);
    }

    public function test_the_catalogue_is_editable_but_never_created_or_deleted(): void
    {
        $achievement = Achievement::firstWhere('key', 'lapped_dog');

        Livewire::test(ListAchievements::class)->assertCanSeeTableRecords([$achievement]);

        Livewire::test(EditAchievement::class, ['record' => $achievement->getRouteKey()])
            ->fillForm(['name' => 'Lapped Pup', 'tier' => 'silver', 'session_types' => ['race'], 'supersedes' => ['good_boy']])
            ->call('save')
            ->assertHasNoFormErrors();

        $achievement->refresh();
        $this->assertSame('Lapped Pup', $achievement->name);
        $this->assertSame(25, $achievement->points);
        $this->assertSame(['race'], $achievement->session_types);
        $this->assertFalse(AchievementResource::canCreate());
    }
}
