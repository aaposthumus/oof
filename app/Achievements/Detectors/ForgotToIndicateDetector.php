<?php

namespace App\Achievements\Detectors;

use App\RaceControl\Offence;

/**
 * Penalised for: moving under braking.
 */
class ForgotToIndicateDetector extends OffencePenaltyDetector
{
    public function key(): string
    {
        return 'forgot_to_indicate';
    }

    protected function offences(): array
    {
        return [Offence::MovingUnderBraking];
    }
}
