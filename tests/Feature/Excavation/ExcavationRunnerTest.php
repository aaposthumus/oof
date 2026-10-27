<?php

namespace Tests\Feature\Excavation;

use App\Enums\MomentCandidateStatus;
use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\ExcavationRunner;
use App\Excavation\Excavator;
use App\Models\Driver;
use App\Models\ExcavationRun;
use App\Models\HistoricMoment;
use App\Models\MomentCandidate;
use App\Models\MomentSensitivity;
use App\Models\Round;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExcavationRunnerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_records_the_run_and_stores_new_candidates(): void
    {
        $round = Round::factory()->create();

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, null, null, 70.0, ['starters' => 6])), new DigParameters(seasons: [2005]));

        $run = ExcavationRun::sole();
        $this->assertSame('test_excavator', $run->excavator);
        $this->assertSame(3, $run->excavator_version);
        $this->assertEquals(['seasons' => [2005], 'from' => null, 'to' => null], $run->parameters);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(1, $run->candidates_found);
        $this->assertDatabaseHas('moment_candidates', [
            'excavation_run_id' => $run->id,
            'type' => 'test_excavator',
            'round_id' => $round->id,
            'driver_id' => null,
            'score' => 100.0,
            'status' => MomentCandidateStatus::New,
            'is_sensitive' => false,
        ]);
        $this->assertSame(['found' => 1, 'created' => 1, 'updated' => 0, 'sensitive' => 0, 'blocked' => 0, 'pruned' => 0, 'ranked' => 1], $result->stats);
        $this->assertEquals(['starters' => 6, 'raw_score' => 70.0], MomentCandidate::sole()->evidence);
    }

    public function test_re_digging_updates_a_round_level_candidate_instead_of_duplicating_it(): void
    {
        $round = Round::factory()->create();
        $runner = app(ExcavationRunner::class);

        $runner->run($this->excavator(new Candidate($round->id, null, null, 50.0, ['starters' => 8])), new DigParameters);
        $result = $runner->run($this->excavator(new Candidate($round->id, null, null, 70.0, ['starters' => 6])), new DigParameters);

        $candidate = MomentCandidate::sole();
        $this->assertSame('100.00', $candidate->score);
        $this->assertEquals(['starters' => 6, 'raw_score' => 70.0], $candidate->evidence);
        $this->assertSame(ExcavationRun::latest('id')->first()->id, $candidate->excavation_run_id);
        $this->assertSame(1, $result->stats['updated']);
    }

    public function test_keeps_driver_level_candidates_apart_per_driver(): void
    {
        $round = Round::factory()->create();
        [$first, $second] = Driver::factory()->count(2)->create();

        app(ExcavationRunner::class)->run($this->excavator(
            new Candidate($round->id, $first->id, null, 80.0, []),
            new Candidate($round->id, $second->id, null, 60.0, []),
        ), new DigParameters);
        app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, $first->id, null, 85.0, [])), new DigParameters(seasons: [$round->season->year + 1]));

        $this->assertDatabaseCount('moment_candidates', 2);
        $this->assertDatabaseHas('moment_candidates', ['driver_id' => $first->id, 'score' => 100.0]);
        $this->assertDatabaseHas('moment_candidates', ['driver_id' => $second->id, 'score' => 0.0]);
    }

    /**
     * @return array<string, array{MomentCandidateStatus}>
     */
    public static function reviewedStatuses(): array
    {
        return [
            'accepted' => [MomentCandidateStatus::Accepted],
            'rejected' => [MomentCandidateStatus::Rejected],
        ];
    }

    #[DataProvider('reviewedStatuses')]
    public function test_never_changes_a_reviewed_status(MomentCandidateStatus $status): void
    {
        $existing = MomentCandidate::factory()->create(['type' => 'test_excavator', 'status' => $status, 'score' => 40]);
        MomentSensitivity::factory()->for($existing->round)->create(['note' => 'A marshal was killed.']);

        app(ExcavationRunner::class)->run($this->excavator(new Candidate($existing->round_id, null, null, 75.0, [])), new DigParameters);

        $existing->refresh();
        $this->assertSame($status, $existing->status);
        $this->assertSame('100.00', $existing->score);
        $this->assertEquals(75.0, $existing->evidence['raw_score']);
        $this->assertTrue($existing->is_sensitive);
    }

    public function test_keeps_candidates_on_a_sensitive_round_as_new_with_the_note(): void
    {
        $round = Round::factory()->create();
        MomentSensitivity::factory()->for($round)->create(['note' => 'A marshal was killed.']);
        MomentSensitivity::factory()->for($round)->create(['note' => 'A spectator was killed.']);

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, null, null, 70.0, [])), new DigParameters);

        $this->assertDatabaseHas('moment_candidates', [
            'round_id' => $round->id,
            'status' => MomentCandidateStatus::New,
            'is_sensitive' => true,
            'sensitivity_note' => 'A marshal was killed. A spectator was killed.',
        ]);
        $this->assertTrue($result->candidates->sole()->isSensitive);
        $this->assertSame(1, $result->stats['sensitive']);
    }

    public function test_a_driver_level_sensitivity_flags_every_candidate_of_its_round_but_no_other_round(): void
    {
        $round = Round::factory()->create();
        $elsewhere = Round::factory()->create();
        [$flagged, $other] = Driver::factory()->count(2)->create();
        MomentSensitivity::factory()->injury()->for($round)->for($flagged)->create(['note' => 'The driver was injured in practice.']);

        app(ExcavationRunner::class)->run($this->excavator(
            new Candidate($round->id, $flagged->id, null, 80.0, []),
            new Candidate($round->id, $other->id, null, 60.0, []),
            new Candidate($round->id, null, null, 50.0, []),
            new Candidate($elsewhere->id, $other->id, null, 40.0, []),
        ), new DigParameters);

        $this->assertSame([3, 1], [
            MomentCandidate::where('round_id', $round->id)->where('is_sensitive', true)->count(),
            MomentCandidate::where('round_id', $elsewhere->id)->where('is_sensitive', false)->count(),
        ]);
        $this->assertSame('The driver was injured in practice.', MomentCandidate::where('driver_id', $other->id)->where('round_id', $round->id)->value('sensitivity_note'));
    }

    public function test_a_minor_entry_flags_its_own_round_but_never_a_related_one(): void
    {
        $round = Round::factory()->create();
        $related = Round::factory()->create(['name' => 'Dutch Grand Prix']);
        $involved = Driver::factory()->create();
        MomentSensitivity::factory()->minor()->for($related)->for($involved)->create(['note' => 'The champion had precautionary checks.']);
        MomentSensitivity::factory()->minor()->for($round)->for($involved)->create(['note' => 'Checks after a crash.']);

        $candidate = $this->runWith(new Candidate($round->id, null, null, 50.0, [], relatedRoundIds: [$related->id], relatedDriverIds: [$involved->id]));

        $this->assertSame('Checks after a crash.', $candidate->sensitivityNote);
    }

    public function test_a_driver_level_block_only_flags_a_round_level_candidate(): void
    {
        $round = Round::factory()->create();
        MomentSensitivity::factory()->blocked()->for($round)->for(Driver::factory())->create(['note' => 'Family asked not to feature this.']);

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, null, null, 50.0, [])), new DigParameters);

        $this->assertSame(0, $result->stats['blocked']);
        $this->assertTrue($result->candidates->sole()->isSensitive);
    }

    public function test_sensitivities_on_related_rounds_flag_with_the_round_named_but_never_block(): void
    {
        $round = Round::factory()->create();
        $related = Round::factory()->create(['name' => 'Dutch Grand Prix']);
        $blockedElsewhere = Round::factory()->create();
        MomentSensitivity::factory()->for($related)->create(['note' => 'A marshal was killed.']);
        MomentSensitivity::factory()->blocked()->for($blockedElsewhere)->create(['note' => 'Blocked.']);

        $result = app(ExcavationRunner::class)->run($this->excavator(
            new Candidate($round->id, null, null, 50.0, [], relatedRoundIds: [$related->id, $blockedElsewhere->id]),
        ), new DigParameters);

        $candidate = $result->candidates->sole();
        $this->assertSame(0, $result->stats['blocked']);
        $this->assertTrue($candidate->isSensitive);
        $this->assertSame("{$related->season->year} Dutch Grand Prix: A marshal was killed. {$blockedElsewhere->season->year} British Grand Prix: Blocked.", $candidate->sensitivityNote);
    }

    public function test_skips_a_blocked_round_and_blocks_its_existing_unreviewed_candidates(): void
    {
        $existing = MomentCandidate::factory()->create(['type' => 'test_excavator']);
        $blockedScore = $existing->score;
        $accepted = MomentCandidate::factory()->accepted()->for($existing->round)->create(['type' => 'test_excavator', 'driver_id' => Driver::factory()]);
        $otherType = MomentCandidate::factory()->for($existing->round)->create(['type' => 'other_excavator']);
        MomentSensitivity::factory()->blocked()->for($existing->round)->create();

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($existing->round_id, null, null, 90.0, [])), new DigParameters);

        $this->assertSame(MomentCandidateStatus::Blocked, $existing->refresh()->status);
        $this->assertSame($blockedScore, $existing->score);
        $this->assertSame(MomentCandidateStatus::Accepted, $accepted->refresh()->status);
        $this->assertSame(MomentCandidateStatus::New, $otherType->refresh()->status);
        $this->assertCount(0, $result->candidates);
        $this->assertSame(1, $result->stats['blocked']);
        $this->assertSame(0, ExcavationRun::where('excavator', 'test_excavator')->sole()->candidates_found);
    }

    public function test_a_driver_level_block_skips_only_that_driver(): void
    {
        $round = Round::factory()->create();
        [$blocked, $other] = Driver::factory()->count(2)->create();
        MomentSensitivity::factory()->blocked()->for($round)->for($blocked)->create();

        app(ExcavationRunner::class)->run($this->excavator(
            new Candidate($round->id, $blocked->id, null, 80.0, []),
            new Candidate($round->id, $other->id, null, 60.0, []),
        ), new DigParameters);

        $this->assertSame([$other->id], MomentCandidate::pluck('driver_id')->all());
    }

    public function test_lifts_the_blocked_status_once_the_block_is_gone(): void
    {
        $candidate = MomentCandidate::factory()->create(['type' => 'test_excavator', 'status' => MomentCandidateStatus::Blocked]);

        app(ExcavationRunner::class)->run($this->excavator(new Candidate($candidate->round_id, null, null, 50.0, [])), new DigParameters);

        $this->assertSame(MomentCandidateStatus::New, $candidate->refresh()->status);
    }

    public function test_scores_candidates_as_percentile_ranks_within_their_type_and_keeps_the_raw_score(): void
    {
        $rounds = Round::factory()->count(4)->create();

        $result = app(ExcavationRunner::class)->run($this->excavator(
            new Candidate($rounds[0]->id, null, null, 30.0, ['n' => 1]),
            new Candidate($rounds[1]->id, null, null, 10.0, ['n' => 2]),
            new Candidate($rounds[2]->id, null, null, 20.0, ['n' => 3]),
            new Candidate($rounds[3]->id, null, null, 30.0, ['n' => 4]),
        ), new DigParameters);

        $this->assertSame([100.0, 100.0, 33.33, 0.0], $result->candidates->pluck('score')->all());
        $this->assertSame([30.0, 30.0, 20.0, 10.0], $result->candidates->pluck('evidence.raw_score')->all());
        $this->assertEquals(
            [$rounds[0]->id => 100.0, $rounds[1]->id => 0.0, $rounds[2]->id => 33.33, $rounds[3]->id => 100.0],
            MomentCandidate::pluck('score', 'round_id')->map(fn (string $score) => (float) $score)->all(),
        );
        $this->assertSame(4, $result->stats['ranked']);
    }

    public function test_recalculates_every_candidate_of_the_type_but_no_other_type(): void
    {
        $old = Round::factory()->for(Season::factory()->create(['year' => 1999]))->create();
        $new = Round::factory()->create();
        $oldCandidate = MomentCandidate::factory()->create(['type' => 'test_excavator', 'round_id' => $old->id, 'score' => 100, 'evidence' => ['raw_score' => 40.0]]);
        $otherType = MomentCandidate::factory()->create(['type' => 'other_excavator', 'score' => 12.34, 'evidence' => ['raw_score' => 5.0]]);

        app(ExcavationRunner::class)->run($this->excavator(new Candidate($new->id, null, null, 60.0, [])), new DigParameters(seasons: [2005]));

        $this->assertSame('0.00', $oldCandidate->refresh()->score);
        $this->assertSame('12.34', $otherType->refresh()->score);
    }

    public function test_ranks_candidates_stored_before_raw_scores_by_their_old_score_and_keeps_it(): void
    {
        $legacy = MomentCandidate::factory()->create([
            'type' => 'test_excavator',
            'round_id' => Round::factory()->for(Season::factory()->create(['year' => 1999])),
            'score' => 50,
            'evidence' => ['starters' => 8],
        ]);
        $round = Round::factory()->create();

        app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, null, null, 70.0, [])), new DigParameters(seasons: [2005]));

        $legacy->refresh();
        $this->assertSame('0.00', $legacy->score);
        $this->assertEquals(['starters' => 8, 'raw_score' => 50.0], $legacy->evidence);
    }

    public function test_ranks_reviewed_candidates_but_not_blocked_ones(): void
    {
        $round = Round::factory()->create();
        MomentCandidate::factory()->accepted()->create(['type' => 'test_excavator', 'evidence' => ['raw_score' => 90.0]]);
        $blocked = MomentCandidate::factory()->create(['type' => 'test_excavator', 'status' => MomentCandidateStatus::Blocked, 'score' => 77, 'evidence' => ['raw_score' => 1.0]]);

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, null, null, 45.0, [])), new DigParameters);

        $this->assertSame(2, $result->stats['ranked']);
        $this->assertSame(0.0, $result->candidates->sole()->score);
        $this->assertSame('77.00', $blocked->refresh()->score);
    }

    public function test_a_dry_run_stores_nothing_but_reports_the_scores_of_a_real_run(): void
    {
        $existing = MomentCandidate::factory()->create(['type' => 'test_excavator', 'score' => 100, 'evidence' => ['raw_score' => 40.0]]);
        $round = Round::factory()->create();

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, null, null, 70.0, [])), new DigParameters, dryRun: true);

        $this->assertSame(100.0, $result->candidates->sole()->score);
        $this->assertSame(1, $result->stats['created']);
        $this->assertSame('100.00', $existing->refresh()->score);
        $this->assertDatabaseCount('excavation_runs', 1);
        $this->assertDatabaseCount('moment_candidates', 1);
    }

    public function test_a_dry_run_stores_nothing(): void
    {
        $round = Round::factory()->create();

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, null, null, 70.0, [])), new DigParameters, dryRun: true);

        $this->assertCount(1, $result->candidates);
        $this->assertDatabaseCount('excavation_runs', 0);
        $this->assertDatabaseCount('moment_candidates', 0);
    }

    public function test_an_injury_flags_a_related_round_only_for_the_drivers_the_story_is_about(): void
    {
        $round = Round::factory()->create();
        $related = Round::factory()->create(['name' => 'Dutch Grand Prix']);
        [$involved, $other] = Driver::factory()->count(2)->create();
        MomentSensitivity::factory()->injury()->for($related)->create(['note' => 'A mechanic was injured.']);
        MomentSensitivity::factory()->injury()->for($related)->for($other)->create(['note' => 'Another driver was injured.']);

        $unrelated = $this->runWith(new Candidate($round->id, null, null, 50.0, [], relatedRoundIds: [$related->id], relatedDriverIds: [$involved->id]));
        MomentSensitivity::factory()->injury()->for($related)->for($involved)->create(['note' => 'The champion was injured.']);
        MomentCandidate::query()->delete();
        $involvedHit = $this->runWith(new Candidate($round->id, null, null, 50.0, [], relatedRoundIds: [$related->id], relatedDriverIds: [$involved->id]));

        $this->assertFalse($unrelated->isSensitive);
        $this->assertTrue($involvedHit->isSensitive);
        $this->assertSame("{$related->season->year} Dutch Grand Prix: The champion was injured.", $involvedHit->sensitivityNote);
    }

    public function test_a_fatal_entry_on_a_related_round_always_flags_but_the_own_round_flags_any_severity(): void
    {
        $round = Round::factory()->create();
        $related = Round::factory()->create(['name' => 'Dutch Grand Prix']);
        MomentSensitivity::factory()->for($related)->create(['note' => 'A marshal was killed.']);
        MomentSensitivity::factory()->injury()->for($round)->create(['note' => 'A mechanic was injured.']);

        $candidate = $this->runWith(new Candidate($round->id, null, null, 50.0, [], relatedRoundIds: [$related->id]));

        $this->assertSame("A mechanic was injured. {$related->season->year} Dutch Grand Prix: A marshal was killed.", $candidate->sensitivityNote);
    }

    public function test_removes_unreviewed_candidates_that_a_dig_no_longer_finds_within_its_seasons(): void
    {
        $inScope = Round::factory()->for(Season::factory()->create(['year' => 2005]))->create();
        $stillFound = Round::factory()->for($inScope->season)->create();
        $outOfScope = Round::factory()->for(Season::factory()->create(['year' => 1999]))->create();
        $stale = MomentCandidate::factory()->create(['type' => 'test_excavator', 'round_id' => $inScope->id]);
        $withMoment = MomentCandidate::factory()->create(['type' => 'test_excavator', 'round_id' => $inScope->id, 'driver_id' => Driver::factory()]);
        HistoricMoment::factory()->create(['moment_candidate_id' => $withMoment->id]);
        $accepted = MomentCandidate::factory()->accepted()->create(['type' => 'test_excavator', 'round_id' => $inScope->id, 'driver_id' => Driver::factory()]);
        $old = MomentCandidate::factory()->create(['type' => 'test_excavator', 'round_id' => $outOfScope->id]);
        $otherType = MomentCandidate::factory()->create(['type' => 'other_excavator', 'round_id' => $inScope->id]);

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($stillFound->id, null, null, 50.0, [])), new DigParameters(seasons: [2005]));

        $this->assertModelMissing($stale);
        $this->assertModelExists($withMoment);
        $this->assertModelExists($accepted);
        $this->assertModelExists($old);
        $this->assertModelExists($otherType);
        $this->assertSame(1, $result->stats['pruned']);
    }

    public function test_a_dry_run_removes_nothing(): void
    {
        $stale = MomentCandidate::factory()->create(['type' => 'test_excavator']);

        $result = app(ExcavationRunner::class)->run($this->excavator(), new DigParameters, dryRun: true);

        $this->assertSame(1, $result->stats['pruned']);
        $this->assertModelExists($stale);
    }

    private function runWith(Candidate $candidate): Candidate
    {
        return app(ExcavationRunner::class)->run($this->excavator($candidate), new DigParameters)->candidates->sole();
    }

    private function excavator(Candidate ...$candidates): Excavator
    {
        return new class($candidates) implements Excavator
        {
            /**
             * @param  list<Candidate>  $candidates
             */
            public function __construct(private array $candidates) {}

            public function key(): string
            {
                return 'test_excavator';
            }

            public function version(): int
            {
                return 3;
            }

            public function dig(DigParameters $parameters): Collection
            {
                return collect($this->candidates);
            }
        };
    }
}
