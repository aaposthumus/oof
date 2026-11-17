<?php

namespace App\Achievements;

use App\Achievements\Facts\DataNeeds;
use Illuminate\Support\Collection;

/**
 * A custom trigger's class (catalogue trigger type custom), for logic the
 * declarative types do not cover. It only reads data; the engine applies
 * the rules and stores the awards.
 */
interface Detector extends DataNeeds
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
