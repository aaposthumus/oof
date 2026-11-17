<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Concerns\ReadsRaceControl;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\SessionType;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * Who caused a red flag or a safety car. Race control never says, so it is
 * inferred from the one car named in an incident, stopped-car or off-track
 * message in the cause window before it: low confidence, always reviewed.
 * Without exactly one such car, a race or sprint gives a driverless case
 * for the CMS to assign or suppress; practice and qualifying give nothing.
 * A red flag just after the chequered flag only closes the session, so it
 * is no cause at all.
 *
 * Attributes: kind (red_flag or safety_car).
 */
class CauseFacts implements FactProvider
{
    use ReadsRaceControl;

    private const CLUES = [MessageType::IncidentNoted, MessageType::CarStopped, MessageType::OffTrack];

    private const KINDS = ['red_flag' => MessageType::RedFlag, 'safety_car' => MessageType::SafetyCarDeployed];

    public function notes(): array
    {
        return ['Inferred from a single-car message just before the deployment; always reviewed.'];
    }

    public function facts(SessionData $data): Collection
    {
        $casesWithoutACar = in_array($data->type(), [SessionType::Race, SessionType::Sprint], true);
        $window = (int) config('achievements.cause_window_seconds');
        $clues = $data->messagesOfType(...self::CLUES)->filter(fn (ClassifiedMessage $message) => $message->soleEntryId() !== null);

        return $data->messagesOfType(...array_values(self::KINDS))
            ->reject(fn (ClassifiedMessage $deployment) => $deployment->is(MessageType::RedFlag) && self::closesTheSession($deployment, $data))
            ->map(function (ClassifiedMessage $deployment) use ($clues, $window, $casesWithoutACar) {
                $issuedAt = $deployment->message->issued_at;
                $before = $clues->filter(fn (ClassifiedMessage $clue) => $clue->message->issued_at->betweenIncluded($issuedAt->copy()->subSeconds($window), $issuedAt))->values();
                $cars = $before->map(fn (ClassifiedMessage $clue) => $clue->soleEntryId())->unique()->values();
                $entryId = $cars->count() === 1 ? $cars->first() : null;

                if ($entryId === null && ! $casesWithoutACar) {
                    return null;
                }

                return new Fact(
                    type: 'cause',
                    entryId: $entryId,
                    instanceKey: "rcm:{$deployment->message->id}",
                    eventKey: "rcm:{$deployment->message->id}",
                    attributes: ['kind' => array_search($deployment->type(), self::KINDS, true)],
                    evidence: FactEvidence::messages([...$before, $deployment], $entryId === null
                        ? ($cars->isEmpty() ? 'No single car was named just before; check the cause.' : 'Several cars were named just before; pick one or suppress.')
                        : 'Inferred from the message just before; race control does not say who caused it.'),
                    confidence: AwardConfidence::Low,
                    reviewReason: $entryId === null ? AwardReviewReason::NoClearCause : AwardReviewReason::LowConfidence,
                );
            })
            ->filter()
            ->values();
    }

    private static function closesTheSession(ClassifiedMessage $redFlag, SessionData $data): bool
    {
        $issuedAt = $redFlag->message->issued_at;

        return $data->messagesOfType(MessageType::ChequeredFlag)->contains(
            fn (ClassifiedMessage $flag) => $flag->message->issued_at->betweenIncluded($issuedAt->copy()->subMinute(), $issuedAt),
        );
    }
}
