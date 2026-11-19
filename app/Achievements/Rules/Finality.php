<?php

namespace App\Achievements\Rules;

use App\Achievements\Detection;
use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Requirements;
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
 * stays in the record, flagged (see Sensitivity). An award that reads
 * Jolpica's lap times (laps led, Led Astray, Clean Sweep) waits for them
 * while OpenF1 stands in, so a published award never flips afterwards.
 */
class Finality
{
    public function __construct(private HeavyCrash $heavyCrash, private Sensitivity $sensitivity, private Requirements $requirements) {}

    /**
     * @return array{0: AwardStatus, 1: AwardReviewReason|null}
     */
    public function judge(Detection $detection, SessionData $data, bool $sessionIsFinal, ?int $driverId, Achievement $achievement): array
    {
        $reason = $detection->reviewReason
            ?? ($detection->confidence === AwardConfidence::Low ? AwardReviewReason::LowConfidence : null)
            ?? ($achievement->shelf === AchievementShelf::Oof && $this->heavyCrash->involves($data, $detection->recipient->entryId) ? AwardReviewReason::HeavyCrash : null)
            ?? ($achievement->shelf !== AchievementShelf::Glory && $this->sensitivity->of($data)['sensitive'] ? AwardReviewReason::SensitiveWeekend : null)
            ?? ($detection->isOpen && ! $sessionIsFinal ? AwardReviewReason::OpenInvestigation : null)
            ?? ($this->awaitsLapTimes($achievement, $data) ? AwardReviewReason::AwaitingLapTimes : null);

        return [$reason === null && $sessionIsFinal ? AwardStatus::Final : AwardStatus::Pending, $reason];
    }

    /**
     * Whether it reads Jolpica's lap times and the race has none yet.
     */
    private function awaitsLapTimes(Achievement $achievement, SessionData $data): bool
    {
        return in_array(DataSource::JolpicaLaps, $this->requirements->for($achievement->key)['sources'], true)
            && ! DataSource::JolpicaLaps->hasRows($data);
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
