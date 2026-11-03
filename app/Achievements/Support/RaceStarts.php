<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A driver's earlier race and sprint starts, from the results: a car that
 * did not start does not count.
 */
class RaceStarts
{
    /**
     * Entries of the driver in races and sprints before the session's own.
     * A race comes after the same weekend's sprint, a sprint after nothing of its weekend.
     *
     * @return Collection<int, DriverEntry>
     */
    public static function before(DriverEntry $entry, SessionData $data, bool $thisSeasonOnly = false): Collection
    {
        $round = $data->session->round;
        $sprintCounts = $data->type() === SessionType::Race;

        return DriverEntry::query()
            ->where('driver_id', $entry->driver_id)
            ->whereHas('result')
            ->whereHas('raceSession', fn (Builder $query) => $query
                ->whereIn('type', [SessionType::Race, SessionType::Sprint])
                ->where(fn (Builder $query) => $query
                    ->whereHas('round', fn (Builder $query) => $query
                        ->where(fn (Builder $query) => $query
                            ->where(fn (Builder $query) => $query->where('season_id', $round->season_id)->where('round_number', '<', $round->round_number))
                            ->when(! $thisSeasonOnly, fn (Builder $query) => $query->orWhereHas('season', fn (Builder $query) => $query->where('year', '<', $data->year())))))
                    ->when($sprintCounts, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                        ->where('round_id', $round->id)
                        ->where('type', SessionType::Sprint)))))
            ->with('result')
            ->get()
            ->reject(fn (DriverEntry $other) => Finish::didNotStart($other->result))
            ->values();
    }
}
