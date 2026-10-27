<?php

namespace App\Excavation\Support;

use App\Enums\SessionType;
use App\Models\Result;
use Illuminate\Database\Query\Builder;

/**
 * Race results joined to their driver entry, round and season, without the
 * rounds no excavator looks at.
 */
class RaceResultQuery
{
    public static function make(): Builder
    {
        return Result::query()
            ->toBase()
            ->join('driver_entries', 'driver_entries.id', '=', 'results.driver_entry_id')
            ->join('race_sessions', 'race_sessions.id', '=', 'driver_entries.race_session_id')
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->join('seasons', 'seasons.id', '=', 'rounds.season_id')
            ->where('race_sessions.type', SessionType::Race)
            ->whereNotIn('rounds.name', config('excavators.excluded_round_names'));
    }
}
