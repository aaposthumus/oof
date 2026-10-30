<?php

namespace App\Achievements;

use Illuminate\Support\Collection;

/**
 * A detector that depends on what the others found, so it runs after the
 * rules have settled the session's other detections.
 */
interface LateDetector
{
    public function key(): string;

    /**
     * @param  Collection<int, Detection>  $detections  The session's settled detections
     * @return Collection<int, Detection>
     */
    public function detectAfter(SessionData $data, Collection $detections): Collection;
}
