<?php

namespace App\Excavation;

use App\Enums\MomentCandidateStatus;
use App\Enums\SensitivityLevel;
use App\Excavation\Excavators\FewestFinishersExcavator;
use App\Excavation\Excavators\FewestStartersExcavator;
use App\Excavation\Excavators\GridToWinClimbExcavator;
use App\Models\ExcavationRun;
use App\Models\MomentCandidate;
use App\Models\MomentSensitivity;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs excavators: records each run, applies moment sensitivities and stores
 * the candidates. Re-digging updates existing candidates; a status set by a
 * reviewer (accepted, rejected) is never changed.
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

    public function run(Excavator $excavator, DigParameters $parameters, bool $dryRun = false): ExcavationResult
    {
        $startedAt = now();
        $found = $excavator->dig($parameters);
        $sensitivities = MomentSensitivity::all()->groupBy('round_id');

        $blocked = 0;
        $candidates = $found
            ->map(function (Candidate $candidate) use ($sensitivities, &$blocked) {
                $matches = $sensitivities->get($candidate->roundId, collect())
                    ->filter(fn (MomentSensitivity $sensitivity) => $sensitivity->driver_id === null || $sensitivity->driver_id === $candidate->driverId);

                if ($matches->contains('level', SensitivityLevel::Blocked)) {
                    $blocked++;

                    return null;
                }

                return $matches->isEmpty() ? $candidate : $candidate->markSensitive($matches->pluck('note')->implode(' '));
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
        ];

        if (! $dryRun) {
            DB::transaction(function () use ($excavator, $parameters, $startedAt, $candidates, $sensitivities, &$stats) {
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

                $run->update(['finished_at' => now(), 'candidates_found' => $candidates->count()]);
            });
        }

        return new ExcavationResult($excavator, $candidates, $stats);
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
            'evidence' => $candidate->evidence,
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
