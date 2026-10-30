<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\SafetyCarPeriods;
use Illuminate\Support\Collection;

/**
 * Safety Car corner: the safety car ran at least the threshold number of laps in one race.
 */
class TookTheLeadDetector implements Detector
{
    public function key(): string
    {
        return 'took_the_lead';
    }

    public function detect(SessionData $data): Collection
    {
        $periods = SafetyCarPeriods::of($data);
        $laps = $periods->sum('laps');

        if ($laps < config('achievements.took_the_lead_laps')) {
            return collect();
        }

        return collect([new Detection(
            achievementKey: $this->key(),
            driverEntryId: null,
            instanceKey: 'session',
            eventKey: 'safety_car',
            evidence: Evidence::messages($periods->flatMap(fn (array $period) => array_filter([$period['deployed'], $period['ended']]))),
            detail: "{$laps} laps",
        )]);
    }
}
