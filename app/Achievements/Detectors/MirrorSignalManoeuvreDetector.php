<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\Evidence;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * Shown blue flags at least the threshold number of times in one race.
 */
class MirrorSignalManoeuvreDetector implements Detector
{
    public function key(): string
    {
        return 'mirror_signal_manoeuvre';
    }

    public function detect(SessionData $data): Collection
    {
        return $data->messagesOfType(MessageType::BlueFlag)
            ->filter(fn (ClassifiedMessage $message) => $message->soleEntryId() !== null)
            ->groupBy(fn (ClassifiedMessage $message) => $message->soleEntryId())
            ->filter(fn (Collection $flags) => $flags->count() >= config('achievements.blue_flags'))
            ->map(fn (Collection $flags, int $entryId) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $entryId,
                instanceKey: 'session',
                eventKey: "blue_flags:{$entryId}",
                evidence: Evidence::messages($flags),
                detail: "{$flags->count()} blue flags",
            ))
            ->values();
    }
}
