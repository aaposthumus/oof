<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * Safety Car corner: deployed at least the threshold number of times in one race.
 */
class SecondHelpingDetector implements Detector
{
    public function key(): string
    {
        return 'second_helping';
    }

    public function detect(SessionData $data): Collection
    {
        $deployments = $data->messagesOfType(MessageType::SafetyCarDeployed);

        if ($deployments->count() < config('achievements.second_helping')) {
            return collect();
        }

        return collect([new Detection(
            achievementKey: $this->key(),
            driverEntryId: null,
            instanceKey: 'session',
            eventKey: 'safety_car',
            evidence: Evidence::messages($deployments),
            detail: "{$deployments->count()} deployments",
        )]);
    }
}
