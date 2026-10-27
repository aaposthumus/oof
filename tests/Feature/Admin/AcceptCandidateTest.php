<?php

namespace Tests\Feature\Admin;

use App\Enums\HistoricMomentStatus;
use App\Enums\MomentCandidateStatus;
use App\Filament\Resources\HistoricMoments\Pages\EditHistoricMoment;
use App\Filament\Resources\MomentCandidates\Pages\ListMomentCandidates;
use App\Filament\Resources\MomentCandidates\Pages\ViewMomentCandidate;
use App\Filament\Widgets\AdminStatsOverview;
use App\Models\HistoricMoment;
use App\Models\MomentCandidate;
use App\Models\Round;
use App\Models\Season;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcceptCandidateTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        Filament::setCurrentPanel('nimda');
    }

    private function candidate(array $attributes = []): MomentCandidate
    {
        $round = Round::factory()->for(Season::firstOrCreate(['year' => 2005]))->create([
            'name' => 'United States Grand Prix',
            'date' => '2005-06-19',
        ]);

        return MomentCandidate::factory()->create([
            'round_id' => $round->id,
            'evidence' => ['starters' => 6, 'entries' => 20],
            ...$attributes,
        ]);
    }

    public function test_accepting_creates_a_linked_draft_moment_and_opens_it(): void
    {
        $candidate = $this->candidate();

        Livewire::test(ListMomentCandidates::class)
            ->callTableAction('accept', $candidate)
            ->assertRedirect();

        $moment = HistoricMoment::sole();

        $this->assertSame($candidate->id, $moment->moment_candidate_id);
        $this->assertSame($candidate->round_id, $moment->round_id);
        $this->assertSame('2005 United States Grand Prix: only 6 cars started', $moment->title);
        $this->assertSame('', $moment->story);
        $this->assertSame('2000s', $moment->era);
        $this->assertSame('2005-06-19', $moment->date->toDateString());
        $this->assertSame(HistoricMomentStatus::Draft, $moment->status);
        $this->assertSame(MomentCandidateStatus::Accepted, $candidate->fresh()->status);
    }

    public function test_accepting_twice_does_not_duplicate_the_moment(): void
    {
        $candidate = $this->candidate();

        $candidate->accept();
        $candidate->accept();

        $this->assertSame(1, HistoricMoment::count());
    }

    public function test_reject_and_block_only_change_the_status(): void
    {
        $rejected = $this->candidate();
        $blocked = $this->candidate();

        Livewire::test(ListMomentCandidates::class)
            ->callTableAction('reject', $rejected)
            ->callTableAction('block', $blocked);

        $this->assertSame(MomentCandidateStatus::Rejected, $rejected->fresh()->status);
        $this->assertSame(MomentCandidateStatus::Blocked, $blocked->fresh()->status);
        $this->assertSame(0, HistoricMoment::count());
    }

    public function test_the_list_defaults_to_new_candidates(): void
    {
        $new = $this->candidate();
        $rejected = $this->candidate(['status' => MomentCandidateStatus::Rejected]);

        Livewire::test(ListMomentCandidates::class)
            ->assertCanSeeTableRecords([$new])
            ->assertCanNotSeeTableRecords([$rejected]);
    }

    public function test_a_sensitive_candidate_shows_its_note_on_the_detail_page(): void
    {
        $candidate = $this->candidate(['is_sensitive' => true, 'sensitivity_note' => 'A spectator was killed.']);

        Livewire::test(ViewMomentCandidate::class, ['record' => $candidate->getRouteKey()])
            ->assertSee('A spectator was killed.')
            ->assertSeeInOrder(['A spectator was killed.', 'Evidence']);
    }

    public function test_the_moment_edit_page_and_dashboard_render_with_the_sensitive_note(): void
    {
        $candidate = $this->candidate(['is_sensitive' => true, 'sensitivity_note' => 'A spectator was killed.']);
        $moment = $candidate->accept();

        Livewire::test(EditHistoricMoment::class, ['record' => $moment->getRouteKey()])
            ->assertSeeInOrder(['A spectator was killed.', 'Title']);

        Livewire::test(AdminStatsOverview::class)->assertSee('Candidates to review');
    }
}
