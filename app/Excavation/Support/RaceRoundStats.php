<?php

namespace App\Excavation\Support;

use App\Excavation\DigParameters;
use Illuminate\Support\Collection;

/**
 * Counts per race: entries, starters (everyone not withdrawn, "W"), classified
 * finishers, and cars running at the flag (classified with status Finished,
 * Lapped or "+N Laps"; a classified car that crashed out does not count).
 * Each round also gets its season's median number of starters, the baseline
 * for judging a field small in its own era.
 */
class RaceRoundStats
{
    /**
     * @return Collection<int, RaceRound> Keyed by round id
     */
    public function forParameters(DigParameters $parameters): Collection
    {
        $query = RaceResultQuery::make()
            ->groupBy('rounds.id', 'seasons.year')
            ->select('rounds.id as round_id', 'seasons.year')
            ->selectRaw('count(*) as entries')
            ->selectRaw("sum(case when results.position_text <> 'W' then 1 else 0 end) as starters")
            ->selectRaw('sum(case when results.is_classified then 1 else 0 end) as classified')
            ->selectRaw("sum(case when results.is_classified and (results.status in ('Finished', 'Lapped') or results.status like '+%') then 1 else 0 end) as running");

        $rows = $parameters->applyTo($query)->get();

        $medians = $rows->groupBy('year')->map(fn (Collection $season) => (float) $season->median('starters'));

        return $rows->mapWithKeys(fn (object $row) => [$row->round_id => new RaceRound(
            roundId: $row->round_id,
            year: $row->year,
            entries: (int) $row->entries,
            starters: (int) $row->starters,
            classified: (int) $row->classified,
            running: (int) $row->running,
            seasonMedianStarters: $medians[$row->year],
        )]);
    }
}
