<?php

namespace App\Achievements\Rules;

use App\Achievements\Detection;
use App\Models\Achievement;
use Illuminate\Support\Collection;

/**
 * Involvement is not fault. An offence or penalty achievement only stands on
 * a message naming exactly one car; anything else is dropped.
 */
class Fairness
{
    /**
     * @param  Collection<int, Detection>  $detections
     * @param  Collection<string, Achievement>  $catalogue  Keyed by key
     * @return Collection<int, Detection>
     */
    public function apply(Collection $detections, Collection $catalogue): Collection
    {
        return $detections
            ->reject(fn (Detection $detection) => $catalogue[$detection->achievementKey]->section->assignsFault()
                && ($detection->carsNamed !== 1 || $detection->driverEntryId === null))
            ->values();
    }
}
