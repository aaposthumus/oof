<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Models\LapTime;

/**
 * The car set the race's fastest lap: Jolpica's fastest-lap rank 1 where the
 * results carry it (from 2004), otherwise the fastest of Jolpica's lap times
 * (from 1996). Equal fastest times share it.
 */
class SetFastestLap implements Predicate
{
    public function sources(): array
    {
        return [DataSource::JolpicaResults, DataSource::JolpicaLaps];
    }

    public function availableFrom(): int
    {
        return 1996;
    }

    public function notes(): array
    {
        return ['Fastest-lap rank from 2004; before that the fastest of Jolpica\'s lap times (from 1996).'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $entry = $data->entry($fact->entryId);
        $rank = $entry?->result?->fastest_lap_rank;

        if ($rank !== null) {
            return $rank === 1;
        }

        $times = $data->lapTimes()->filter(fn (LapTime $lap) => $lap->time_millis !== null);

        if ($entry === null || $times->isEmpty()) {
            return false;
        }

        return $times->where('driver_entry_id', $entry->id)->min('time_millis') === $times->min('time_millis');
    }
}
