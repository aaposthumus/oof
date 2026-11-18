<?php

namespace App\Achievements\Triggers;

use App\Achievements\Detection;
use App\Achievements\SeasonData;
use Illuminate\Support\Collection;

/**
 * How the engine finds a season-scope achievement, once the season's final
 * round is final. It only reads data; the engine stores the awards.
 */
interface SeasonTrigger
{
    /**
     * @return Collection<int, Detection>
     */
    public function detectSeason(SeasonData $season): Collection;
}
