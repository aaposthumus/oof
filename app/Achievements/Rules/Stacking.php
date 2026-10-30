<?php

namespace App\Achievements\Rules;

use App\Achievements\Detection;
use App\Enums\AwardConfidence;
use App\Models\Achievement;
use Illuminate\Support\Collection;

/**
 * An achievement that does not stack is awarded once per driver per session:
 * its instances are merged into one, keeping all their evidence.
 */
class Stacking
{
    /**
     * @param  Collection<int, Detection>  $detections
     * @param  Collection<string, Achievement>  $catalogue  Keyed by key
     * @return Collection<int, Detection>
     */
    public function apply(Collection $detections, Collection $catalogue): Collection
    {
        return $detections
            ->groupBy(fn (Detection $detection) => $catalogue[$detection->achievementKey]->stacks || $detection->driverEntryId === null
                ? $detection->key()
                : "{$detection->achievementKey}|{$detection->driverEntryId}")
            ->map(fn (Collection $instances) => $instances->count() === 1 ? $instances->first() : $this->merge($instances->values()))
            ->values();
    }

    /**
     * @param  Collection<int, Detection>  $instances
     */
    private function merge(Collection $instances): Detection
    {
        $first = $instances->first();
        $details = $instances->pluck('detail')->filter()->unique()->values()->all();
        $evidence = $first->evidence;

        foreach ($instances->skip(1) as $instance) {
            foreach (['messages', 'rows'] as $part) {
                if (isset($instance->evidence[$part])) {
                    $evidence[$part] = collect([...($evidence[$part] ?? []), ...$instance->evidence[$part]])->unique()->values()->all();
                }
            }
        }

        $confidences = $instances->pluck('confidence');

        return $first->with([
            'instanceKey' => 'session',
            'detail' => implode(' · ', ["×{$instances->count()}", ...$details]),
            'evidence' => $evidence,
            'confidence' => $confidences->contains(AwardConfidence::High) ? AwardConfidence::High : ($confidences->contains(AwardConfidence::Medium) ? AwardConfidence::Medium : AwardConfidence::Low),
            'reviewReason' => $instances->pluck('reviewReason')->filter()->first(),
            'isOpen' => $instances->contains('isOpen', true),
        ]);
    }
}
