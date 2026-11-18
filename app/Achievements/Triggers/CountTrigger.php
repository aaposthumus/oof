<?php

namespace App\Achievements\Triggers;

use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\SessionData;
use Illuminate\Support\Collection;

/**
 * Type count: count or sum the facts that meet the conditions, per
 * recipient (car, team) or for the whole session, against a threshold. One detection per group that
 * reaches it, with the evidence of all its facts. Templates get {count}
 * (facts) and {total} (the sum, or the count).
 *
 * Options besides the common ones:
 * - per: recipient (default; a car for a driver achievement, a team for a team one) or session
 * - sum: the attribute to add up; the facts are counted without it
 * - at_least: the threshold
 */
class CountTrigger extends DeclarativeTrigger
{
    protected const OPTIONS = ['per', 'sum', 'at_least'];

    public function detect(SessionData $data, Collection $settled): Collection
    {
        $facts = $this->selected($data);
        $groups = ($this->definition['per'] ?? 'recipient') === 'session'
            ? collect([$facts])->filter(fn (Collection $group) => $group->isNotEmpty())
            : $facts->groupBy(fn (Fact $fact) => $this->recipientOf($fact, $data)->key() ?? '');

        return $groups
            ->map(function (Collection $group) use ($data) {
                $sum = $this->definition['sum'] ?? null;
                $total = $sum === null ? $group->count() : $group->sum(fn (Fact $fact) => $fact->get($sum));

                if ($total < $this->definition['at_least']) {
                    return null;
                }

                return $this->detection(
                    $group->first(),
                    $data,
                    ['count' => $group->count(), 'total' => $total],
                    FactEvidence::merge($group->map(fn (Fact $fact) => $fact->evidence)),
                    fromSeveral: true,
                );
            })
            ->filter()
            ->values();
    }
}
