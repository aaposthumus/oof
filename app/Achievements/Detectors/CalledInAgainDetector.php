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
class CalledInAgainDetector implements Detector
{
    public function key(): string
    {
        return 'called_in_again';
    }

    public function detect(SessionData $data): Collection
    {
        $deployments = $data->messagesOfType(MessageType::SafetyCarDeployed);

        if ($deployments->count() < config('achievements.called_in_again')) {
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
