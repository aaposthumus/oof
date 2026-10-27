<?php

namespace App\Excavation;

use App\Enums\MomentCandidateStatus;
use App\Enums\SensitivityLevel;
use App\Excavation\Excavators\AgeRecordExcavator;
use App\Excavation\Excavators\ClosestFinishExcavator;
use App\Excavation\Excavators\CuriousRetirementExcavator;
use App\Excavation\Excavators\DisqualificationExcavator;
use App\Excavation\Excavators\DominantWinExcavator;
use App\Excavation\Excavators\FewestFinishersExcavator;
use App\Excavation\Excavators\FewestStartersExcavator;
use App\Excavation\Excavators\FirstTimeWinnerExcavator;
use App\Excavation\Excavators\GridToWinClimbExcavator;
use App\Excavation\Excavators\MostRetirementsExcavator;
use App\Excavation\Excavators\PodiumDroughtEndedExcavator;
use App\Excavation\Excavators\ShortestRaceExcavator;
use App\Excavation\Excavators\SlowestPitStopExcavator;
use App\Excavation\Excavators\StartMilestoneExcavator;
use App\Excavation\Excavators\SurpriseWinnerExcavator;
use App\Excavation\Excavators\TitleDeciderExcavator;
use App\Excavation\Excavators\WinDroughtEndedExcavator;
use App\Excavation\Support\PercentileRank;
use App\Models\ExcavationRun;
use App\Models\MomentCandidate;
use App\Models\MomentSensitivity;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs excavators: records each run, applies moment sensitivities and stores
 * the candidates. Re-digging updates existing candidates; a status set by a
 * reviewer (accepted, rejected) is never changed. Candidate scores are
 * percentile ranks within their type, recalculated after every dig.
 *
 * A sensitivity blocks the candidates of its round, or only that driver's
 * when it names one. It flags every candidate it concerns: those of its
 * round (round-level candidates also by a driver's sensitivity) and those
 * whose story spans its round.
 */
class ExcavationRunner
{
    /**
     * Excavators in the order they run.
     *
     * @var list<class-string<Excavator>>
     */
    public const EXCAVATORS = [
        FewestStartersExcavator::class,
        FewestFinishersExcavator::class,
        GridToWinClimbExcavator::class,
        SurpriseWinnerExcavator::class,
        FirstTimeWinnerExcavator::class,
        WinDroughtEndedExcavator::class,
        PodiumDroughtEndedExcavator::class,
        MostRetirementsExcavator::class,
        CuriousRetirementExcavator::class,
        DisqualificationExcavator::class,
        ShortestRaceExcavator::class,
        DominantWinExcavator::class,
        ClosestFinishExcavator::class,
        TitleDeciderExcavator::class,
        AgeRecordExcavator::class,
        StartMilestoneExcavator::class,
        SlowestPitStopExcavator::class,
    ];

    public function __construct(private Container $container) {}

    /**
     * @return array<string, Excavator> Keyed by excavator key
     */
    public function excavators(): array
    {
        return collect(self::EXCAVATORS)
            ->map(fn (string $class) => $this->container->make($class))
            ->keyBy(fn (Excavator $excavator) => $excavator->key())
            ->all();
    }

    /**
     * Everything is written inside one transaction. A dry run rolls it back, so
     * its created and updated counts and percentile scores match a real run.
     */
    public function run(Excavator $excavator, DigParameters $parameters, bool $dryRun = false): ExcavationResult
    {
        $startedAt = now();
        $found = $excavator->dig($parameters);
        $sensitivities = MomentSensitivity::with('round.season')->get()->groupBy('round_id');

        $blocked = 0;
        $candidates = $found
            ->map(function (Candidate $candidate) use ($sensitivities, &$blocked) {
                $own = $sensitivities->get($candidate->roundId, collect());

                if ($own->contains(fn (MomentSensitivity $sensitivity) => $sensitivity->level === SensitivityLevel::Blocked && ($sensitivity->driver_id === null || $sensitivity->driver_id === $candidate->driverId))) {
                    $blocked++;

                    return null;
                }

                $matches = collect([$candidate->roundId, ...$candidate->relatedRoundIds])
                    ->unique()
                    ->flatMap(fn (int $roundId) => $sensitivities->get($roundId, collect()))
                    ->filter(fn (MomentSensitivity $sensitivity) => $this->flags($sensitivity, $candidate));

                return $matches->isEmpty() ? $candidate : $candidate->markSensitive($matches
                    ->map(fn (MomentSensitivity $sensitivity) => $sensitivity->round_id === $candidate->roundId
                        ? $sensitivity->note
                        : "{$sensitivity->round->season->year} {$sensitivity->round->name}: {$sensitivity->note}")
                    ->unique()
                    ->implode(' '));
            })
            ->filter()
            ->sortByDesc('score')
            ->values();

        $stats = [
            'found' => $found->count(),
            'created' => 0,
            'updated' => 0,
            'sensitive' => $candidates->where('isSensitive', true)->count(),
            'blocked' => $blocked,
            'ranked' => 0,
        ];

        DB::beginTransaction();

        try {
            $run = ExcavationRun::create([
                'excavator' => $excavator->key(),
                'excavator_version' => $excavator->version(),
                'parameters' => $parameters->toArray(),
                'started_at' => $startedAt,
            ]);

            foreach ($candidates as $candidate) {
                $stored = $this->store($run, $excavator, $candidate);
                $stats[$stored->wasRecentlyCreated ? 'created' : 'updated']++;
            }

            $this->blockExisting($excavator, $sensitivities->flatten()->where('level', SensitivityLevel::Blocked));
            $stats['ranked'] = $this->recalculateScores($excavator->key());

            $run->update(['finished_at' => now(), 'candidates_found' => $candidates->count()]);

            $scores = MomentCandidate::query()
                ->where('type', $excavator->key())
                ->get(['round_id', 'driver_id', 'score'])
                ->mapWithKeys(fn (MomentCandidate $stored) => [$this->candidateKey($stored->round_id, $stored->driver_id) => (float) $stored->score]);
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return new ExcavationResult(
            $excavator,
            $candidates->map(fn (Candidate $candidate) => $candidate->withRankedScore($scores[$this->candidateKey($candidate->roundId, $candidate->driverId)])),
            $stats,
        );
    }

    /**
     * A round-level sensitivity concerns every candidate, a driver's only that
     * driver's candidates and the round-level ones.
     */
    private function flags(MomentSensitivity $sensitivity, Candidate $candidate): bool
    {
        return $sensitivity->driver_id === null || $candidate->driverId === null || $sensitivity->driver_id === $candidate->driverId;
    }

    private function store(ExcavationRun $run, Excavator $excavator, Candidate $candidate): MomentCandidate
    {
        $model = MomentCandidate::query()
            ->where('type', $excavator->key())
            ->where('round_id', $candidate->roundId)
            ->where('driver_id', $candidate->driverId)
            ->firstOrNew([], [
                'type' => $excavator->key(),
                'round_id' => $candidate->roundId,
                'driver_id' => $candidate->driverId,
                'status' => MomentCandidateStatus::New,
            ]);

        $model->fill([
            'excavation_run_id' => $run->id,
            'season_team_id' => $candidate->seasonTeamId,
            'score' => $candidate->score,
            'evidence' => [...$candidate->evidence, 'raw_score' => $candidate->score],
            'is_sensitive' => $candidate->isSensitive,
            'sensitivity_note' => $candidate->sensitivityNote,
        ]);

        // Blocked is set by excavation, not by a reviewer, so it lifts once the block is gone.
        if ($model->status === MomentCandidateStatus::Blocked) {
            $model->status = MomentCandidateStatus::New;
        }

        $model->save();

        return $model;
    }

    /**
     * Replace the score of every candidate of the type, not only those just
     * dug, by the percentile rank of its raw score. Blocked candidates are
     * left out. Candidates stored before raw scores existed keep their old
     * score as raw score.
     *
     * @return int Number of candidates ranked
     */
    private function recalculateScores(string $type): int
    {
        $candidates = MomentCandidate::query()
            ->where('type', $type)
            ->where('status', '!=', MomentCandidateStatus::Blocked)
            ->get();

        $rawScores = $candidates->mapWithKeys(fn (MomentCandidate $candidate) => [$candidate->id => (float) ($candidate->evidence['raw_score'] ?? $candidate->score)]);
        $percentiles = PercentileRank::of($rawScores->all());

        foreach ($candidates as $candidate) {
            $candidate->evidence = [...$candidate->evidence, 'raw_score' => $rawScores[$candidate->id]];
            $candidate->score = $percentiles[$candidate->id];
            $candidate->save();
        }

        return $candidates->count();
    }

    private function candidateKey(int $roundId, ?int $driverId): string
    {
        return $roundId.':'.($driverId ?? 0);
    }

    /**
     * Mark unreviewed candidates of this excavator that are now blocked.
     *
     * @param  Collection<int, MomentSensitivity>  $blocks
     */
    private function blockExisting(Excavator $excavator, Collection $blocks): void
    {
        foreach ($blocks as $block) {
            MomentCandidate::query()
                ->where('type', $excavator->key())
                ->where('round_id', $block->round_id)
                ->when($block->driver_id !== null, fn (Builder $query) => $query->where('driver_id', $block->driver_id))
                ->where('status', MomentCandidateStatus::New)
                ->update(['status' => MomentCandidateStatus::Blocked]);
        }
    }
}
