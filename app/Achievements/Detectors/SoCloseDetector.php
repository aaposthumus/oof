<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\QualifyingPhases;
use App\Achievements\Support\TrackLimitDeletions;
use Illuminate\Support\Collection;

/**
 * The best lap of Q3 deleted: a Q3 time deleted for track limits that beats
 * the time the car is classified with.
 */
class SoCloseDetector implements Detector
{
    public function key(): string
    {
        return 'so_close';
    }

    public function detect(SessionData $data): Collection
    {
        return TrackLimitDeletions::of($data)
            ->filter(function (array $deletion) use ($data) {
                $q3 = $data->entry($deletion['entry_id'])?->qualifyingResult?->q3_millis;

                return $deletion['message']->phase === 3
                    && $deletion['time'] !== null
                    && $q3 !== null
                    && TrackLimitDeletions::toMillis($deletion['time']) < $q3;
            })
            ->sortBy(fn (array $deletion) => TrackLimitDeletions::toMillis($deletion['time']))
            ->unique('entry_id')
            ->map(fn (array $deletion) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $deletion['entry_id'],
                instanceKey: 'q3',
                eventKey: "q3_deletion:{$deletion['entry_id']}",
                evidence: [
                    ...Evidence::messages([$deletion['message']]),
                    'note' => 'Classified Q3 time: '.QualifyingPhases::seconds($data->entry($deletion['entry_id'])->qualifyingResult->q3_millis),
                ],
                detail: $deletion['time'],
            ))
            ->values();
    }
}
