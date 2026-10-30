<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\IncidentThread;
use App\RaceControl\MessageType;
use App\RaceControl\Offence;
use Illuminate\Support\Collection;

/**
 * An offence achievement: the stewards penalised one car for one of these
 * offences. Being noted or investigated is not enough. The penalty itself
 * becomes a penalty achievement, which the cascade folds into this one.
 */
abstract class OffencePenaltyDetector implements Detector
{
    /**
     * @return list<Offence>
     */
    abstract protected function offences(): array;

    /**
     * Whether a penalty for one of the offences counts, by its reason as worded.
     */
    protected function counts(?string $reason): bool
    {
        return true;
    }

    public function detect(SessionData $data): Collection
    {
        return collect($data->threads())
            ->filter(fn (IncidentThread $thread) => in_array($thread->offence, $this->offences(), true) && $this->counts($thread->reason))
            ->flatMap(fn (IncidentThread $thread) => collect($thread->penalties())
                ->filter(fn (ClassifiedMessage $penalty) => $penalty->soleEntryId() !== null)
                ->unique(fn (ClassifiedMessage $penalty) => $penalty->soleEntryId())
                ->map(fn (ClassifiedMessage $penalty) => new Detection(
                    achievementKey: $this->key(),
                    driverEntryId: $penalty->soleEntryId(),
                    instanceKey: "incident:{$thread->id()}",
                    eventKey: $thread->eventKey(),
                    evidence: Evidence::messages($thread->messagesFor($penalty->soleEntryId())),
                    detail: self::otherPenalty($penalty),
                    carsNamed: $penalty->carsNamed(),
                )))
            ->values();
    }

    /**
     * A penalty that has no achievement of its own, as a detail: "3 places", "disqualified".
     */
    private static function otherPenalty(ClassifiedMessage $penalty): ?string
    {
        return match ($penalty->type()) {
            MessageType::GridPenalty => $penalty->classification->detail,
            MessageType::PitLaneStart => 'pit lane start',
            MessageType::Disqualification => 'disqualified',
            default => null,
        };
    }
}
