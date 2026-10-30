<?php

namespace App\Achievements\Detectors;

use App\RaceControl\MessageType;

/**
 * Caused a safety car (races and sprints only: practice has test deployments).
 */
class CalledTheTowTruckDetector extends CauseDetector
{
    public function key(): string
    {
        return 'called_the_tow_truck';
    }

    protected function type(): MessageType
    {
        return MessageType::SafetyCarDeployed;
    }
}
