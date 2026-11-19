<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\Concerns\ReadsResults;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;

/**
 * Classified in the first place outside the points of that era
 * (achievements.first_place_outside_the_points: P6 in the 1950s, P7 to
 * 2002, P9 to 2009, P11 since 2010), with no points: in the 1950s a P6
 * could score the fastest-lap point.
 */
class FirstPlaceOutsideThePoints implements Predicate
{
    use ReadsResults;

    public function notes(): array
    {
        return ['The first non-scoring place per era: P6 in 1950-1959, P7 in 1960-2002, P9 in 2003-2009, P11 from 2010.'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $result = $data->entry($fact->entryId)?->result;

        return $result?->position !== null
            && $result->position === self::placeIn($data->year())
            && (float) $result->points === 0.0;
    }

    public static function placeIn(int $year): ?int
    {
        $place = null;

        foreach (config('achievements.first_place_outside_the_points') as $from => $position) {
            if ($year >= $from) {
                $place = $position;
            }
        }

        return $place;
    }
}
