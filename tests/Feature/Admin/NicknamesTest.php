<?php

namespace Tests\Feature\Admin;

use App\Enums\EvolutionStatus;
use App\Enums\NicknameStatus;
use App\Filament\Resources\DriverNicknames\Pages\CreateDriverNickname;
use App\Filament\Resources\DriverNicknames\Pages\EditDriverNickname;
use App\Filament\Resources\DriverNicknames\Pages\ListDriverNicknames;
use App\Filament\Resources\NicknameEvolutions\Pages\ListNicknameEvolutions;
use App\Models\DriverNickname;
use App\Models\NicknameEvolution;
use App\Models\Round;
use App\Models\Season;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NicknamesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        Filament::setCurrentPanel('nimda');
    }

    public function test_correcting_a_nickname_changes_the_row_in_place(): void
    {
        $nickname = DriverNickname::factory()->create(['nickname' => 'LindBud']);

        Livewire::test(ListDriverNicknames::class)->assertCanSeeTableRecords([$nickname]);
        Livewire::test(EditDriverNickname::class, ['record' => $nickname->getRouteKey()])
            ->fillForm(['nickname' => 'LindBuddy', 'motto' => 'Born to bloom', 'is_draft' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['LindBuddy', 'Born to bloom', false, NicknameStatus::Active, 1], [
            $nickname->fresh()->nickname, $nickname->fresh()->motto, $nickname->fresh()->is_draft, $nickname->fresh()->status, DriverNickname::count(),
        ]);
    }

    public function test_a_next_stage_is_planned_and_a_second_active_nickname_is_refused(): void
    {
        $current = DriverNickname::factory()->create();

        Livewire::test(CreateDriverNickname::class)
            ->fillForm(['driver_id' => $current->driver_id, 'evolves_from_id' => $current->id, 'nickname' => 'NextOne'])
            ->call('create')
            ->assertHasNoFormErrors();

        $planned = DriverNickname::firstWhere('nickname', 'NextOne');
        $this->assertSame([NicknameStatus::Planned, 2, $current->id], [$planned->status, $planned->stage, $planned->evolves_from_id]);

        Livewire::test(CreateDriverNickname::class)
            ->fillForm(['driver_id' => $current->driver_id, 'nickname' => 'SecondActive'])
            ->call('create')
            ->assertNotified('Nickname not added');

        $this->assertNull(DriverNickname::firstWhere('nickname', 'SecondActive'));
    }

    public function test_an_evolution_is_approved_only_with_an_off_season_date_and_can_be_dismissed(): void
    {
        $evolution = NicknameEvolution::factory()->create();
        Round::factory()->for($evolution->season)->create(['round_number' => 1, 'date' => '2027-11-28']);
        Round::factory()->for(Season::firstOrCreate(['year' => 2028]))->create(['round_number' => 1, 'date' => '2028-03-05']);

        Livewire::test(ListNicknameEvolutions::class)
            ->assertCanSeeTableRecords([$evolution])
            ->callTableAction('approve', $evolution, ['effective_on' => '2027-07-01'])
            ->assertNotified('Not approved');
        $this->assertSame(EvolutionStatus::Proposed, $evolution->fresh()->status);

        Livewire::test(ListNicknameEvolutions::class)->callTableAction('approve', $evolution, ['effective_on' => '2027-12-20']);
        $this->assertSame([EvolutionStatus::Approved, '2027-12-20'], [$evolution->fresh()->status, $evolution->fresh()->effective_on->toDateString()]);

        Livewire::test(ListNicknameEvolutions::class)->callTableAction('dismiss', $evolution);
        $this->assertSame(EvolutionStatus::Dismissed, $evolution->fresh()->status);
    }
}
