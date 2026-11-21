<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Achievements\Support\RunningOrder;

/**
 * The car retired while leading (Bubble Burst): out of the race before the
 * flag (see Retired), after completing at least one lap, and P1 at the end
 * of its last completed lap or of the lap before it. The lap before counts
 * because a car slowing with its failure is already shown lower at the end
 * of its last lap (Leclerc, Spain 2022: P4 on lap 27 after leading lap 26).
 * Positions from Jolpica's lap times (from 1996), OpenF1 standing in until
 * they are in.
 */
class RetiredWhileLeading implements Predicate
{
    public function sources(): array
    {
        return [DataSource::JolpicaResults, DataSource::JolpicaLaps, DataSource::OpenF1Positions, DataSource::OpenF1Laps];
    }

    public function availableFrom(): int
    {
        return 1996;
    }

    public function notes(): array
    {
        return ['Leading from Jolpica lap times (from 1996): P1 at the end of the last completed lap or the one before it; OpenF1 stands in until a race\'s Jolpica laps are in, and the award waits for them.'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $entry = $data->entry($fact->entryId);
        $completed = (int) $entry?->result?->laps_completed;

        if ($entry?->result === null || ! Finish::retired($entry->result) || $completed < 1) {
            return false;
        }

        return RunningOrder::positionAt($data, $entry->id, $completed) === 1
            || ($completed > 1 && RunningOrder::positionAt($data, $entry->id, $completed - 1) === 1);
    }
}
