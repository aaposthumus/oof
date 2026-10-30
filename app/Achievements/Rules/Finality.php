<?php

namespace App\Achievements\Rules;

use App\Achievements\Detection;
use App\Achievements\SessionData;
use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use App\Models\MomentSensitivity;
use Carbon\CarbonImmutable;

/**
 * Whether an award is final or waits. It waits while its investigation is
 * open or the session is not final yet, and always when it is inferred
 * (low confidence), disputed, or on a sensitive weekend, until a person
 * confirms it.
 */
class Finality
{
    /**
     * Driver IDs with a sensitivity per round; 0 for the whole round.
     *
     * @var array<int, list<int>>
     */
    private array $sensitivities = [];

    /**
     * @return array{0: AwardStatus, 1: AwardReviewReason|null}
     */
    public function judge(Detection $detection, SessionData $data, bool $sessionIsFinal, ?int $driverId): array
    {
        $reason = $detection->reviewReason
            ?? ($detection->confidence === AwardConfidence::Low ? AwardReviewReason::LowConfidence : null)
            ?? ($this->isSensitive($data, $driverId) ? AwardReviewReason::SensitiveWeekend : null)
            ?? ($detection->isOpen && ! $sessionIsFinal ? AwardReviewReason::OpenInvestigation : null);

        return [$reason === null && $sessionIsFinal ? AwardStatus::Final : AwardStatus::Pending, $reason];
    }

    public function sessionIsFinal(SessionData $data, ?CarbonImmutable $now = null): bool
    {
        return $data->isFinal($now ?? CarbonImmutable::now());
    }

    private function isSensitive(SessionData $data, ?int $driverId): bool
    {
        $drivers = $this->sensitivities[$data->session->round_id] ??= MomentSensitivity::query()
            ->where('round_id', $data->session->round_id)
            ->pluck('driver_id')
            ->map(fn (?int $id) => $id ?? 0)
            ->all();

        return in_array(0, $drivers, true) || ($driverId !== null && in_array($driverId, $drivers, true));
    }
}
