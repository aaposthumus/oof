<?php

namespace App\Achievements\Rules;

use App\Achievements\Detection;
use App\Achievements\SessionData;
use App\Enums\AchievementShelf;
use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use Carbon\CarbonImmutable;

/**
 * Whether an award is final or waits. It waits while its investigation is
 * open or the session is not final yet, and always when it is inferred
 * (low confidence), disputed, an OOF or Safety Car award on a sensitive
 * weekend, or an OOF award for a driver who retired in a heavy crash, until
 * a person confirms it. Glory on a sensitive weekend does not wait: a win
 * stays in the record, flagged (see Sensitivity).
 */
class Finality
{
    public function __construct(private HeavyCrash $heavyCrash, private Sensitivity $sensitivity) {}

    /**
     * @return array{0: AwardStatus, 1: AwardReviewReason|null}
     */
    public function judge(Detection $detection, SessionData $data, bool $sessionIsFinal, ?int $driverId, Achievement $achievement): array
    {
        $reason = $detection->reviewReason
            ?? ($detection->confidence === AwardConfidence::Low ? AwardReviewReason::LowConfidence : null)
            ?? ($achievement->shelf === AchievementShelf::Oof && $this->heavyCrash->involves($data, $detection->driverEntryId) ? AwardReviewReason::HeavyCrash : null)
            ?? ($achievement->shelf !== AchievementShelf::Glory && $this->sensitivity->of($data)['sensitive'] ? AwardReviewReason::SensitiveWeekend : null)
            ?? ($detection->isOpen && ! $sessionIsFinal ? AwardReviewReason::OpenInvestigation : null);

        return [$reason === null && $sessionIsFinal ? AwardStatus::Final : AwardStatus::Pending, $reason];
    }

    /**
     * A season award is decided once the season is final, from final awards
     * and data: it waits only when inferred (low confidence).
     *
     * @return array{0: AwardStatus, 1: AwardReviewReason|null}
     */
    public function judgeSeason(Detection $detection): array
    {
        $reason = $detection->reviewReason ?? ($detection->confidence === AwardConfidence::Low ? AwardReviewReason::LowConfidence : null);

        return [$reason === null ? AwardStatus::Final : AwardStatus::Pending, $reason];
    }

    public function sessionIsFinal(SessionData $data, ?CarbonImmutable $now = null): bool
    {
        return $data->isFinal($now ?? CarbonImmutable::now());
    }
}
