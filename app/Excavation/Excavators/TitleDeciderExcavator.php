<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use App\Models\DriverStanding;
use App\Models\Round;
use Illuminate\Support\Collection;

/**
 * Drivers' championships decided in the final round, or by very few points.
 * Only completed seasons count: the last round on the calendar must have
 * standings. Before 1991, dropped scores make "still open before the final
 * round" an estimate. The story spans the season, so all its rounds are
 * related for sensitivities, and the champion and runner-up are the drivers
 * whose injuries elsewhere in the season matter. Drivers level on points
 * before the final round count like a lead change and earn the full margin
 * score, since that race alone decided the title.
 */
class TitleDeciderExcavator implements Excavator
{
    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'title_decider';
    }

    public function version(): int
    {
        return 2;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $races = $this->timeline->byRound();
        $standings = DriverStanding::query()
            ->toBase()
            ->join('drivers', 'drivers.id', '=', 'driver_standings.driver_id')
            ->whereNotNull('driver_standings.position')
            ->get(['driver_standings.round_id', 'driver_standings.driver_id', 'driver_standings.position', 'driver_standings.points', 'drivers.given_name', 'drivers.family_name'])
            ->groupBy('round_id');

        return Round::query()
            ->with('season')
            ->orderBy('round_number')
            ->get()
            ->groupBy('season_id')
            ->filter(fn (Collection $rounds) => $parameters->includes($rounds->first()->season->year))
            ->map(function (Collection $rounds) use ($standings, $races) {
                $final = $rounds->last();
                $penultimate = $rounds->where('round_number', '<', $final->round_number)->last();
                $after = $standings->get($final->id);
                $before = $penultimate === null ? null : $standings->get($penultimate->id);

                if ($after === null || $before === null || ! $races->has($final->id)) {
                    return null;
                }

                return $this->judge($final, $rounds, $before->sortBy('position')->values(), $after->sortBy('position')->values(), $races[$final->id]);
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, Round>  $rounds
     * @param  Collection<int, object>  $before  Standings before the final round, leader first
     * @param  Collection<int, object>  $after  Final standings, champion first
     * @param  Collection<int, object>  $finalRace  CareerTimeline rows of the final race
     */
    private function judge(Round $final, Collection $rounds, Collection $before, Collection $after, Collection $finalRace): ?Candidate
    {
        $champion = $after->first();
        $runnerUp = $after->get(1);
        $leaderBefore = $before->first();
        $secondBefore = $before->get(1);

        if ($runnerUp === null || $secondBefore === null) {
            return null;
        }

        $winPoints = (float) ($finalRace->firstWhere('is_win', true)?->points ?? $finalRace->max('points'));
        $maxFinalPoints = (float) $finalRace->max('points');
        $margin = (float) $champion->points - (float) $runnerUp->points;
        $leadBefore = (float) $leaderBefore->points - (float) $secondBefore->points;

        $leadChange = $leaderBefore->driver_id !== $champion->driver_id;
        $levelBefore = $leadBefore == 0.0;
        $openBefore = $leadChange || $levelBefore || $leadBefore <= $maxFinalPoints;
        $close = $winPoints > 0 && $margin <= (float) config('excavators.title_decider.max_margin_wins') * $winPoints;

        if (! $openBefore && ! $close) {
            return null;
        }

        $base = match (true) {
            $leadChange || $levelBefore => config('excavators.title_decider.lead_change_score'),
            $openBefore => config('excavators.title_decider.open_score'),
            default => config('excavators.title_decider.close_score'),
        };
        $closeness = $levelBefore ? 1 : ($winPoints > 0 ? 1 - min(1, $margin / $winPoints) : 0);

        return new Candidate(
            roundId: $final->id,
            driverId: (int) $champion->driver_id,
            seasonTeamId: $finalRace->firstWhere('driver_id', (int) $champion->driver_id)?->season_team_id,
            score: round($base + (float) config('excavators.title_decider.margin_score') * $closeness, 2),
            evidence: [
                'champion' => "{$champion->given_name} {$champion->family_name}",
                'runner_up' => "{$runnerUp->given_name} {$runnerUp->family_name}",
                'final_margin' => $margin,
                'leader_before_final' => "{$leaderBefore->given_name} {$leaderBefore->family_name}",
                'lead_before_final' => $leadBefore,
                'max_points_in_final' => $maxFinalPoints,
                'lead_change' => $leadChange,
                'level_on_points_before_final' => $levelBefore,
                'open_before_final' => $openBefore,
                'estimate_with_dropped_scores' => $final->season->year <= (int) config('excavators.title_decider.dropped_scores_until'),
            ],
            relatedRoundIds: $rounds->pluck('id')->reject(fn (int $id) => $id === $final->id)->values()->all(),
            relatedDriverIds: [(int) $champion->driver_id, (int) $runnerUp->driver_id],
        );
    }
}
