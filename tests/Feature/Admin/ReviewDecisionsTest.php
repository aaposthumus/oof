<?php

namespace Tests\Feature\Admin;

use App\Enums\HistoricMomentStatus;
use App\Enums\MomentCandidateStatus;
use App\Filament\Resources\MomentCandidates\Pages\ListMomentCandidates;
use App\Filament\Resources\MomentCandidates\Pages\ViewMomentCandidate;
use App\Models\HistoricMoment;
use App\Models\MomentCandidate;
use App\Models\Round;
use App\Models\Season;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReviewDecisionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        Filament::setCurrentPanel('nimda');
    }

    private function candidate(MomentCandidateStatus $status = MomentCandidateStatus::New): MomentCandidate
    {
        $round = Round::factory()->for(Season::firstOrCreate(['year' => 2005]))->create(['name' => 'United States Grand Prix', 'date' => '2005-06-19']);

        return MomentCandidate::factory()->create(['round_id' => $round->id, 'evidence' => ['starters' => 6, 'entries' => 20], 'status' => $status]);
    }

    private function list(): Testable
    {
        return Livewire::test(ListMomentCandidates::class)->removeTableFilter('status');
    }

    /**
     * @return array<string, array{MomentCandidateStatus, list<string>, list<string>}>
     */
    public static function visibleActions(): array
    {
        return [
            'new' => [MomentCandidateStatus::New, ['accept', 'reject', 'block'], ['reset']],
            'accepted' => [MomentCandidateStatus::Accepted, ['reject', 'block', 'reset'], ['accept']],
            'rejected' => [MomentCandidateStatus::Rejected, ['accept', 'block', 'reset'], ['reject']],
            'blocked' => [MomentCandidateStatus::Blocked, ['accept', 'reject', 'reset'], ['block']],
        ];
    }

    /**
     * @param  list<string>  $visible
     * @param  list<string>  $hidden
     */
    #[DataProvider('visibleActions')]
    public function test_shows_only_the_actions_that_change_something_on_rows_and_the_detail_page(MomentCandidateStatus $status, array $visible, array $hidden): void
    {
        $candidate = $this->candidate($status);
        $list = $this->list();
        $detail = Livewire::test(ViewMomentCandidate::class, ['record' => $candidate->getRouteKey()]);

        foreach ($visible as $action) {
            $list->assertTableActionVisible($action, $candidate);
            $detail->assertActionVisible($action);
        }

        foreach ($hidden as $action) {
            $list->assertTableActionHidden($action, $candidate);
            $detail->assertActionHidden($action);
        }
    }

    public function test_resets_a_blocked_or_rejected_candidate_to_new_from_the_row_and_the_detail_page(): void
    {
        $blocked = $this->candidate(MomentCandidateStatus::Blocked);
        $rejected = $this->candidate(MomentCandidateStatus::Rejected);

        $this->list()->callTableAction('reset', $blocked);
        Livewire::test(ViewMomentCandidate::class, ['record' => $rejected->getRouteKey()])->callAction('reset');

        $this->assertSame(MomentCandidateStatus::New, $blocked->fresh()->status);
        $this->assertSame(MomentCandidateStatus::New, $rejected->fresh()->status);
    }

    public function test_resets_in_bulk_and_skips_candidates_that_are_already_new(): void
    {
        $blocked = $this->candidate(MomentCandidateStatus::Blocked);
        $new = $this->candidate();
        $accepted = $this->candidate(MomentCandidateStatus::Accepted);
        $moment = HistoricMoment::factory()->create(['moment_candidate_id' => $accepted->id, 'story' => '', 'status' => HistoricMomentStatus::Draft]);

        $this->list()->callTableBulkAction('reset', [$blocked, $new, $accepted]);

        $this->assertSame([MomentCandidateStatus::New], MomentCandidate::pluck('status')->unique()->values()->all());
        $this->assertModelMissing($moment);
    }

    public function test_bulk_reject_and_block_skip_candidates_with_that_status_already(): void
    {
        $new = $this->candidate();
        $blocked = $this->candidate(MomentCandidateStatus::Blocked);

        $this->list()->callTableBulkAction('reject', [$new, $blocked]);

        $this->assertSame(MomentCandidateStatus::Rejected, $new->fresh()->status);
        $this->assertSame(MomentCandidateStatus::Rejected, $blocked->fresh()->status);
    }

    public function test_the_bulk_action_for_the_filtered_status_is_hidden(): void
    {
        $this->candidate(MomentCandidateStatus::Blocked);

        Livewire::test(ListMomentCandidates::class)
            ->filterTable('status', 'blocked')
            ->assertTableBulkActionHidden('block')
            ->assertTableBulkActionVisible('reset')
            ->filterTable('status', 'new')
            ->assertTableBulkActionHidden('reset')
            ->assertTableBulkActionVisible('block');
    }

    /**
     * @return array<string, array{string, MomentCandidateStatus}>
     */
    public static function leavingActions(): array
    {
        return [
            'reset' => ['reset', MomentCandidateStatus::New],
            'reject' => ['reject', MomentCandidateStatus::Rejected],
            'block' => ['block', MomentCandidateStatus::Blocked],
        ];
    }

    #[DataProvider('leavingActions')]
    public function test_leaving_accepted_deletes_an_untouched_draft_moment(string $action, MomentCandidateStatus $expected): void
    {
        $candidate = $this->candidate();
        $moment = $candidate->accept();

        $test = $this->list()->callTableAction($action, $candidate);

        $this->assertSame($expected, $candidate->fresh()->status);
        $this->assertModelMissing($moment);
        $test->assertHasNoTableActionErrors();
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function workedOnMoments(): array
    {
        return [
            'edited' => [['story' => 'Gethin won by a hair.']],
            'published' => [['status' => HistoricMomentStatus::Published]],
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    #[DataProvider('workedOnMoments')]
    public function test_leaving_accepted_keeps_an_edited_or_published_moment_and_asks_first(array $changes): void
    {
        $candidate = $this->candidate();
        $moment = $candidate->accept();
        $moment->update($changes);

        $test = $this->list()->mountTableAction('reset', $candidate);

        $this->assertStringContainsString('will remain after this change', $test->instance()->getMountedAction()->getModalDescription());
        $this->assertTrue($test->instance()->getMountedAction()->isConfirmationRequired());
        $this->assertSame(MomentCandidateStatus::Accepted, $candidate->fresh()->status);

        $test->callMountedTableAction();

        $this->assertSame(MomentCandidateStatus::New, $candidate->fresh()->status);
        $this->assertModelExists($moment);
        $this->assertSame($candidate->id, $moment->fresh()->moment_candidate_id);
    }

    public function test_accepting_again_reuses_the_kept_moment(): void
    {
        $candidate = $this->candidate();
        $moment = $candidate->accept();
        $moment->update(['story' => 'Written.']);
        $candidate->fresh()->resetToNew();

        $this->assertSame($moment->id, $candidate->fresh()->accept()->id);
        $this->assertSame(1, HistoricMoment::count());
    }

    public function test_block_always_asks_for_confirmation(): void
    {
        $candidate = $this->candidate();

        $this->list()->mountTableAction('block', $candidate);

        $this->assertSame(MomentCandidateStatus::New, $candidate->fresh()->status);
    }

    public function test_resetting_a_candidate_that_was_never_accepted_needs_no_confirmation(): void
    {
        $candidate = $this->candidate(MomentCandidateStatus::Rejected);

        $this->list()->mountTableAction('reset', $candidate);

        $this->assertSame(MomentCandidateStatus::New, $candidate->fresh()->status);
    }
}
