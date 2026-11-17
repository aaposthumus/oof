<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\TeamLineages\Pages\CreateTeamLineage;
use App\Filament\Resources\TeamLineages\Pages\EditTeamLineage;
use App\Filament\Resources\TeamLineages\Pages\ListTeamLineages;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\TeamLineage;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamLineagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        Filament::setCurrentPanel('nimda');
    }

    private function seasonTeam(int $year, string $constructorId): SeasonTeam
    {
        $team = Team::firstOrCreate(['slug' => $constructorId], ['name' => ucfirst($constructorId)]);

        return SeasonTeam::factory()->create(['season_id' => Season::firstOrCreate(['year' => $year])->id, 'team_id' => $team->id, 'jolpica_constructor_id' => $constructorId, 'name' => ucfirst($constructorId)]);
    }

    public function test_lists_the_rules(): void
    {
        Livewire::test(ListTeamLineages::class)
            ->assertCanSeeTableRecords(TeamLineage::query()->where('team_slug', 'arrows')->get());
    }

    public function test_a_new_rule_re_points_the_season_teams_at_once(): void
    {
        $eurobrun = $this->seasonTeam(1989, 'eurobrun');
        $this->seasonTeam(1991, 'brun');
        TeamLineage::factory()->create(['jolpica_constructor_id' => 'brun', 'team_slug' => 'brun']);

        Livewire::test(CreateTeamLineage::class)
            ->fillForm(['jolpica_constructor_id' => 'eurobrun', 'team_slug' => 'brun', 'to_year' => 1990])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Lineage applied');

        $this->assertSame('brun', $eurobrun->fresh()->team->slug);
    }

    public function test_an_overlapping_rule_for_the_same_constructor_is_refused(): void
    {
        Livewire::test(CreateTeamLineage::class)
            ->fillForm(['jolpica_constructor_id' => 'alfa', 'team_slug' => 'alfa_romeo', 'from_year' => 2023, 'to_year' => 2024])
            ->call('create')
            ->assertHasFormErrors(['jolpica_constructor_id']);
    }

    public function test_a_rule_that_would_merge_an_unmapped_constructor_is_rolled_back(): void
    {
        $this->seasonTeam(1989, 'eurobrun');
        $this->seasonTeam(1991, 'brun');
        $rule = TeamLineage::factory()->create(['jolpica_constructor_id' => 'brun', 'team_slug' => 'brun']);
        TeamLineage::factory()->create(['jolpica_constructor_id' => 'eurobrun', 'team_slug' => 'brun']);

        Livewire::test(EditTeamLineage::class, ['record' => $rule->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('Lineage not changed');

        $this->assertModelExists($rule);
    }
}
