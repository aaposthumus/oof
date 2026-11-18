<?php

namespace App\Achievements\Triggers;

use App\Achievements\SessionData;
use Illuminate\Support\Collection;

/**
 * Type manual: never detected. The achievement is only awarded by a person
 * in the CMS (Built Different; see AchievementAward::awardByHand()), and the
 * engine keeps such an award as the person's decision.
 */
class ManualTrigger implements Trigger
{
    public function isLate(): bool
    {
        return false;
    }

    public function detect(SessionData $data, Collection $settled): Collection
    {
        return collect();
    }
}
