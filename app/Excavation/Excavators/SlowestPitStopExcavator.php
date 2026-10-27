<?php

namespace App\Excavation\Excavators;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\RaceResultQuery;
use App\Excavation\Support\RaceStatuses;
use Illuminate\Support\Collection;

/**
 * The longest pit stops (data from 2011). Durations are pit-lane time, not
 * stationary time. Red-flag stops, repairs in the garage (very long stops)
 * and stops after which the driver soon retired are left out. A driver's
 * longest stop of a race counts.
 */
class SlowestPitStopExcavator implements Excavator
{
    public function key(): string
    {
        return 'slowest_pit_stop';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $minMillis = (float) config('excavators.slowest_pit_stop.min_seconds') * 1000;
        $maxMillis = (float) config('excavators.slowest_pit_stop.max_seconds') * 1000;
        $minRatio = (float) config('excavators.slowest_pit_stop.min_ratio');
        $redFlagMinStops = (int) config('excavators.slowest_pit_stop.red_flag_min_stops');
        $retireWithinLaps = (int) config('excavators.slowest_pit_stop.retire_within_laps');

        $query = RaceResultQuery::make()
            ->join('pit_stops', 'pit_stops.driver_entry_id', '=', 'driver_entries.id')
            ->whereNotNull('pit_stops.duration_millis')
            ->select([
                'rounds.id as round_id', 'driver_entries.driver_id', 'driver_entries.season_team_id',
                'pit_stops.stop', 'pit_stops.lap', 'pit_stops.duration_millis', 'results.status', 'results.laps_completed',
            ]);

        return $parameters->applyTo($query)->get()
            ->groupBy('round_id')
            ->flatMap(function (Collection $stops) use ($minMillis, $maxMillis, $minRatio, $redFlagMinStops, $retireWithinLaps) {
                $typical = $this->lowerQuartile($stops->pluck('duration_millis')->map(fn ($millis) => (int) $millis));
                $isLong = fn (object $stop) => $typical > 0 && $stop->duration_millis >= $minRatio * $typical;
                $longLaps = $stops->filter($isLong)->countBy('lap');
                $isRedFlag = fn (object $stop) => $longLaps->only([$stop->lap - 1, $stop->lap, $stop->lap + 1])->sum() >= $redFlagMinStops;
                $retiredSoon = fn (object $stop) => ! RaceStatuses::isRunning($stop->status) && $stop->laps_completed !== null && $stop->laps_completed <= $stop->lap + $retireWithinLaps;

                return $stops
                    ->filter(fn (object $stop) => $stop->duration_millis >= $minMillis && $stop->duration_millis <= $maxMillis && $isLong($stop) && ! $isRedFlag($stop) && ! $retiredSoon($stop))
                    ->groupBy('driver_id')
                    ->map(fn (Collection $driverStops) => [$driverStops->sortByDesc('duration_millis')->first(), $typical, $driverStops->count()]);
            })
            ->map(fn (array $hit) => new Candidate(
                roundId: (int) $hit[0]->round_id,
                driverId: (int) $hit[0]->driver_id,
                seasonTeamId: (int) $hit[0]->season_team_id,
                score: round($hit[0]->duration_millis / $hit[1], 2),
                evidence: [
                    'duration_ms' => (int) $hit[0]->duration_millis,
                    'lap' => (int) $hit[0]->lap,
                    'stop' => (int) $hit[0]->stop,
                    'typical_stop_ms' => $hit[1],
                    'ratio' => round($hit[0]->duration_millis / $hit[1], 2),
                    'long_stops' => $hit[2],
                    'status' => $hit[0]->status,
                ],
            ))
            ->values();
    }

    /**
     * @param  Collection<int, int>  $durations
     */
    private function lowerQuartile(Collection $durations): int
    {
        $sorted = $durations->sort()->values();

        return (int) ($sorted[intdiv($sorted->count(), 4)] ?? 0);
    }
}
