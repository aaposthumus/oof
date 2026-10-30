<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\TrackLimitDeletions;
use Illuminate\Support\Collection;

/**
 * A lap time deleted for track limits, once per deleted time.
 */
class ColouringOutsideTheLinesDetector implements Detector
{
    public function key(): string
    {
        return 'colouring_outside_the_lines';
    }

    public function detect(SessionData $data): Collection
    {
        return TrackLimitDeletions::of($data)
            ->map(fn (array $deletion) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $deletion['entry_id'],
                instanceKey: "deletion:{$deletion['message']->message->id}",
                eventKey: "track_limits:{$deletion['entry_id']}",
                evidence: Evidence::messages([$deletion['message']]),
            ));
    }
}
