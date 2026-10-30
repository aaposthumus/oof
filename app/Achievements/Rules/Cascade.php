<?php

namespace App\Achievements\Rules;

use App\Achievements\Detection;
use App\Enums\AchievementSection;
use App\Models\Achievement;
use Illuminate\Support\Collection;

/**
 * One event, one achievement. Within a car's event, a detection superseded
 * by a more specific one is removed; a penalty is folded into the detail of
 * what replaced it, so "Bumper Cars" shows as "Bumper Cars · 10 s".
 */
class Cascade
{
    /**
     * @param  Collection<int, Detection>  $detections
     * @param  Collection<string, Achievement>  $catalogue  Keyed by key
     * @return Collection<int, Detection>
     */
    public function apply(Collection $detections, Collection $catalogue): Collection
    {
        return $detections
            ->groupBy(fn (Detection $detection) => "{$detection->driverEntryId}|{$detection->eventKey}")
            ->flatMap(fn (Collection $event) => $this->settle($event->values(), $catalogue))
            ->values();
    }

    /**
     * @param  Collection<int, Detection>  $event
     * @param  Collection<string, Achievement>  $catalogue
     * @return Collection<int, Detection>
     */
    private function settle(Collection $event, Collection $catalogue): Collection
    {
        $present = $event->pluck('achievementKey')->unique();
        $supersededBy = [];

        foreach ($present as $key) {
            foreach ($catalogue[$key]->supersedes as $superseded) {
                if ($superseded !== $key && $present->contains($superseded) && ! $this->supersedes($catalogue, $superseded, $key)) {
                    $supersededBy[$superseded] ??= $key;
                }
            }
        }

        if ($supersededBy === []) {
            return $event;
        }

        // A key superseded by a key that is itself superseded hands over to the one left standing.
        $survivor = function (string $key) use (&$survivor, $supersededBy): string {
            return isset($supersededBy[$key]) ? $survivor($supersededBy[$key]) : $key;
        };

        $folded = [];

        foreach ($event as $detection) {
            if (isset($supersededBy[$detection->achievementKey]) && $catalogue[$detection->achievementKey]->section === AchievementSection::Penalty && $detection->detail !== null) {
                $folded[$survivor($detection->achievementKey)][] = $detection->detail;
            }
        }

        return $event
            ->reject(fn (Detection $detection) => isset($supersededBy[$detection->achievementKey]))
            ->map(function (Detection $detection) use ($folded) {
                $details = array_unique(array_filter([$detection->detail, ...($folded[$detection->achievementKey] ?? [])]));

                return $details === [] ? $detection : $detection->with(['detail' => implode(' · ', $details)]);
            })
            ->values();
    }

    /**
     * @param  Collection<string, Achievement>  $catalogue
     */
    private function supersedes(Collection $catalogue, string $key, string $other): bool
    {
        return in_array($other, $catalogue[$key]->supersedes, true);
    }
}
