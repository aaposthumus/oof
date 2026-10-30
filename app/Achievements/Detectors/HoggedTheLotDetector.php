<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use App\Models\Position;
use App\Models\SessionLap;

/**
 * Grand Chelem: pole, win, fastest lap and leading every lap. Leading is
 * read from OpenF1's positions at the end of each lap (the start of the
 * next); without positions and laps the race cannot be judged.
 */
class HoggedTheLotDetector extends ResultDetector
{
    public function key(): string
    {
        return 'hogged_the_lot';
    }

    protected function judge(DriverEntry $entry, SessionData $data): string|false|null
    {
        $result = $entry->result;

        if ($result->position !== 1 || Finish::isDisqualified($result) || $result->fastest_lap_rank !== 1 || ! $this->tookPole($entry, $data)) {
            return false;
        }

        return $this->ledEveryLap($entry, $data) ? null : false;
    }

    private function tookPole(DriverEntry $entry, SessionData $data): bool
    {
        $qualifying = $data->roundSession(SessionType::Qualifying);
        $pole = $qualifying?->entries->first(fn (DriverEntry $other) => $other->qualifyingResult?->position === 1);

        return $pole !== null ? $pole->driver_id === $entry->driver_id : $entry->result->grid === 1;
    }

    private function ledEveryLap(DriverEntry $entry, SessionData $data): bool
    {
        $positions = $data->positions()->where('driver_entry_id', $entry->id)->values();
        $lapEnds = $data->laps()
            ->where('driver_entry_id', $entry->id)
            ->filter(fn (SessionLap $lap) => $lap->lap_number > 1 && $lap->started_at !== null)
            ->pluck('started_at');

        if ($positions->isEmpty() || $lapEnds->count() < $entry->result->laps_completed - 1) {
            return false;
        }

        return $lapEnds->every(fn ($lapEnd) => $positions->last(fn (Position $position) => $position->recorded_at->lessThanOrEqualTo($lapEnd))?->position === 1);
    }
}
