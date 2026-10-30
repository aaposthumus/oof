<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\IncidentThread;
use App\RaceControl\Offence;
use Illuminate\Support\Collection;

/**
 * A penalty achievement, once per penalty. A track-limits penalty belongs to
 * the car's track-limits event, so it replaces the deletions and the black
 * and white flag that led to it; any other belongs to its incident.
 */
abstract class PenaltyDetector implements Detector
{
    abstract protected function matches(ClassifiedMessage $penalty): bool;

    abstract protected function detail(ClassifiedMessage $penalty): string;

    public function detect(SessionData $data): Collection
    {
        return collect($data->threads())
            ->flatMap(fn (IncidentThread $thread) => collect($thread->penalties())
                ->filter(fn (ClassifiedMessage $penalty) => $penalty->soleEntryId() !== null && $this->matches($penalty))
                ->unique(fn (ClassifiedMessage $penalty) => $penalty->soleEntryId())
                ->map(fn (ClassifiedMessage $penalty) => new Detection(
                    achievementKey: $this->key(),
                    driverEntryId: $penalty->soleEntryId(),
                    instanceKey: "rcm:{$penalty->message->id}",
                    eventKey: $thread->offence === Offence::TrackLimits ? "track_limits:{$penalty->soleEntryId()}" : $thread->eventKey(),
                    evidence: Evidence::messages($thread->messagesFor($penalty->soleEntryId())),
                    detail: $this->detail($penalty),
                    carsNamed: $penalty->carsNamed(),
                )))
            ->values();
    }
}
