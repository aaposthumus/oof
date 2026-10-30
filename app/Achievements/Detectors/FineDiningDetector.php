<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\PitStops;
use App\Enums\SessionType;
use Illuminate\Support\Collection;

/**
 * The slowest pit stop of the weekend (race and sprint), by measured
 * stationary time. Awarded in the session the stop was made in.
 */
class FineDiningDetector implements Detector
{
    public function key(): string
    {
        return 'fine_dining';
    }

    public function detect(SessionData $data): Collection
    {
        $slowest = collect([SessionType::Race, SessionType::Sprint])
            ->map(fn (SessionType $type) => $data->roundSession($type))
            ->filter()
            ->flatMap(fn (SessionData $session) => PitStops::countable($session))
            ->sortByDesc('stop_duration_millis')
            ->first();

        if ($slowest === null || $slowest->race_session_id !== $data->session->id) {
            return collect();
        }

        return collect([new Detection(
            achievementKey: $this->key(),
            driverEntryId: $slowest->driver_entry_id,
            instanceKey: "pit:{$slowest->id}",
            eventKey: "pit:{$slowest->id}",
            evidence: Evidence::rows('openf1', [PitStops::row($slowest)], 'The slowest measured stop of the weekend.'),
            detail: PitStops::seconds($slowest->stop_duration_millis),
        )]);
    }
}
