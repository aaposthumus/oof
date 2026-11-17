<?php

namespace App\Achievements\Triggers;

use App\Achievements\Detection;
use App\Achievements\SessionData;
use Illuminate\Support\Collection;

/**
 * How the engine finds one achievement in a session, built from its
 * catalogue entry. Triggers only read data; the engine applies the rules
 * and stores the awards.
 */
interface Trigger
{
    /**
     * Whether it depends on the session's other detections, so it runs
     * after the rules have settled them.
     */
    public function isLate(): bool;

    /**
     * @param  Collection<int, Detection>  $settled  The session's settled detections (late triggers only)
     * @return Collection<int, Detection>
     */
    public function detect(SessionData $data, Collection $settled): Collection;
}
