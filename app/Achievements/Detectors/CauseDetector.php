<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\Evidence;
use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\SessionType;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * Who caused a red flag or safety car. Race control never says, so this is
 * inferred from the one car named in an incident, stopped-car or off-track
 * message just before it: low confidence, always reviewed. Without exactly
 * one such car, a race or sprint sends a driverless case to the CMS to
 * assign or suppress; practice and qualifying give nothing (weather, debris
 * or track problems give no achievement).
 */
abstract class CauseDetector implements Detector
{
    private const CLUES = [MessageType::IncidentNoted, MessageType::CarStopped, MessageType::OffTrack];

    abstract protected function type(): MessageType;

    /**
     * Whether a deployment counts at all, before looking for its cause.
     */
    protected function counts(ClassifiedMessage $deployment, SessionData $data): bool
    {
        return true;
    }

    public function detect(SessionData $data): Collection
    {
        $casesWithoutACar = in_array($data->type(), [SessionType::Race, SessionType::Sprint], true);
        $window = (int) config('achievements.cause_window_seconds');
        $clues = $data->messagesOfType(...self::CLUES)->filter(fn (ClassifiedMessage $message) => $message->soleEntryId() !== null);

        return $data->messagesOfType($this->type())
            ->filter(fn (ClassifiedMessage $deployment) => $this->counts($deployment, $data))
            ->map(function (ClassifiedMessage $deployment) use ($clues, $window, $casesWithoutACar) {
                $issuedAt = $deployment->message->issued_at;
                $before = $clues->filter(fn (ClassifiedMessage $clue) => $clue->message->issued_at->betweenIncluded($issuedAt->copy()->subSeconds($window), $issuedAt))->values();
                $cars = $before->map(fn (ClassifiedMessage $clue) => $clue->soleEntryId())->unique()->values();
                $entryId = $cars->count() === 1 ? $cars->first() : null;

                if ($entryId === null && ! $casesWithoutACar) {
                    return null;
                }

                return new Detection(
                    achievementKey: $this->key(),
                    driverEntryId: $entryId,
                    instanceKey: "rcm:{$deployment->message->id}",
                    eventKey: "rcm:{$deployment->message->id}",
                    evidence: Evidence::messages([...$before, $deployment], $entryId === null
                        ? ($cars->isEmpty() ? 'No single car was named just before; check the cause.' : 'Several cars were named just before; pick one or suppress.')
                        : 'Inferred from the message just before; race control does not say who caused it.'),
                    confidence: AwardConfidence::Low,
                    reviewReason: $entryId === null ? AwardReviewReason::NoClearCause : AwardReviewReason::LowConfidence,
                );
            })
            ->filter()
            ->values();
    }
}
