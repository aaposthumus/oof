<?php

namespace App\Achievements\Triggers;

use App\Achievements\Facts\Fact;
use App\Achievements\SessionData;
use Illuminate\Support\Collection;

/**
 * Type match: one detection per fact that meets the conditions.
 *
 * Options besides the common ones:
 * - distinct: attributes to keep the first fact of ("entry", "incident")
 * - order_by: the attribute that decides which fact comes first (ascending)
 */
class MatchTrigger extends DeclarativeTrigger
{
    protected const OPTIONS = ['distinct', 'order_by'];

    public function detect(SessionData $data, Collection $settled): Collection
    {
        $facts = $this->selected($data);

        if (isset($this->definition['order_by'])) {
            $facts = $facts->sortBy(fn (Fact $fact) => $fact->get($this->definition['order_by']))->values();
        }

        if (isset($this->definition['distinct'])) {
            $facts = $facts->unique(fn (Fact $fact) => implode('|', array_map(fn (string $attribute) => (string) $fact->get($attribute), $this->definition['distinct'])))->values();
        }

        return $facts->map(fn (Fact $fact) => $this->detection($fact, $data))->values();
    }
}
