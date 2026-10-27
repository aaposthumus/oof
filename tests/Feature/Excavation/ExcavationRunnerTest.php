<?php

namespace Tests\Feature\Excavation;

use App\Enums\MomentCandidateStatus;
use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\ExcavationRunner;
use App\Excavation\Excavator;
use App\Models\Driver;
use App\Models\ExcavationRun;
use App\Models\MomentCandidate;
use App\Models\MomentSensitivity;
use App\Models\Round;
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
            'score' => 70.0,
            'status' => MomentCandidateStatus::New,
            'is_sensitive' => false,
        ]);
        $this->assertSame(['found' => 1, 'created' => 1, 'updated' => 0, 'sensitive' => 0, 'blocked' => 0], $result->stats);
    }

    public function test_re_digging_updates_a_round_level_candidate_instead_of_duplicating_it(): void
    {
        $round = Round::factory()->create();
        $runner = app(ExcavationRunner::class);

        $runner->run($this->excavator(new Candidate($round->id, null, null, 50.0, ['starters' => 8])), new DigParameters);
        $result = $runner->run($this->excavator(new Candidate($round->id, null, null, 70.0, ['starters' => 6])), new DigParameters);

        $candidate = MomentCandidate::sole();
        $this->assertSame('70.00', $candidate->score);
        $this->assertSame(['starters' => 6], $candidate->evidence);
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
        app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, $first->id, null, 85.0, [])), new DigParameters);

        $this->assertDatabaseCount('moment_candidates', 2);
        $this->assertDatabaseHas('moment_candidates', ['driver_id' => $first->id, 'score' => 85.0]);
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
        $this->assertSame('75.00', $existing->score);
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

    public function test_a_driver_level_sensitivity_flags_only_that_driver(): void
    {
        $round = Round::factory()->create();
        [$flagged, $other] = Driver::factory()->count(2)->create();
        MomentSensitivity::factory()->for($round)->for($flagged)->create();

        app(ExcavationRunner::class)->run($this->excavator(
            new Candidate($round->id, $flagged->id, null, 80.0, []),
            new Candidate($round->id, $other->id, null, 60.0, []),
            new Candidate($round->id, null, null, 50.0, []),
        ), new DigParameters);

        $this->assertSame([$flagged->id], MomentCandidate::where('is_sensitive', true)->pluck('driver_id')->all());
    }

    public function test_skips_a_blocked_round_and_blocks_its_existing_unreviewed_candidates(): void
    {
        $existing = MomentCandidate::factory()->create(['type' => 'test_excavator']);
        $accepted = MomentCandidate::factory()->accepted()->for($existing->round)->create(['type' => 'test_excavator', 'driver_id' => Driver::factory()]);
        $otherType = MomentCandidate::factory()->for($existing->round)->create(['type' => 'other_excavator']);
        MomentSensitivity::factory()->blocked()->for($existing->round)->create();

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($existing->round_id, null, null, 90.0, [])), new DigParameters);

        $this->assertSame(MomentCandidateStatus::Blocked, $existing->refresh()->status);
        $this->assertNotEquals('90.00', $existing->score);
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

    public function test_a_dry_run_stores_nothing(): void
    {
        $round = Round::factory()->create();

        $result = app(ExcavationRunner::class)->run($this->excavator(new Candidate($round->id, null, null, 70.0, [])), new DigParameters, dryRun: true);

        $this->assertCount(1, $result->candidates);
        $this->assertDatabaseCount('excavation_runs', 0);
        $this->assertDatabaseCount('moment_candidates', 0);
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
