<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Models\SessionLap;
use Illuminate\Support\Collection;

/**
 * Most laps driven in a practice session.
 */
class OvertimeDetector extends MostInSessionDetector
{
    public function key(): string
    {
        return 'overtime';
    }

    protected function counts(SessionData $data): Collection
    {
        return $data->laps()
            ->filter(fn (SessionLap $lap) => $lap->driver_entry_id !== null)
            ->countBy('driver_entry_id');
    }

    protected function detail(int $count): string
    {
        return "{$count} laps";
    }
}
