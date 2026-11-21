<?php

namespace App\Achievements\Facts;

use App\Achievements\SessionData;

/**
 * A fact provider whose facts exist only where the data measured them, and
 * that can say how much of a session it measured (pit stops: the share of
 * pit visits with a stationary time). A season aggregate can require a
 * minimum coverage in every session it reads (option coverage), so a season
 * measured only in places gives nothing rather than a doubtful winner.
 */
interface MeasuresCoverage
{
    /**
     * The measured share of the session, from 0 to 1, or null when there
     * was nothing to measure.
     */
    public function coverage(SessionData $data): ?float;
}
