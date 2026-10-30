<?php

namespace App\Achievements;

use Illuminate\Support\Collection;

/**
 * Finds one achievement in a session. Detectors only read data; the engine
 * applies the rules and stores the awards.
 */
interface Detector
{
    /**
     * The key of the achievement in the catalogue.
     */
    public function key(): string;

    /**
     * @return Collection<int, Detection>
     */
    public function detect(SessionData $data): Collection;
}
