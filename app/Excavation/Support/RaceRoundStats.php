<?php

namespace App\Excavation\Support;

use App\Enums\SessionType;
use App\Excavation\DigParameters;
use App\Models\Result;
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
        $query = Result::query()
            ->toBase()
            ->join('driver_entries', 'driver_entries.id', '=', 'results.driver_entry_id')
            ->join('race_sessions', 'race_sessions.id', '=', 'driver_entries.race_session_id')
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->join('seasons', 'seasons.id', '=', 'rounds.season_id')
            ->where('race_sessions.type', SessionType::Race)
            ->whereNotIn('rounds.name', config('excavators.excluded_round_names'))
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
