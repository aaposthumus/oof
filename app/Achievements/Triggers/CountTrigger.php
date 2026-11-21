<?php

namespace App\Achievements\Triggers;

use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\SessionData;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Type count: count or sum the facts that meet the conditions, per
 * recipient (car, team), per driver or for the whole session, against a
 * threshold. One detection per group that reaches it, with the evidence of
 * all its facts. Templates get {count} (facts) and {total} (the sum, the
 * distinct values, or the count).
 *
 * Options besides the common ones:
 * - per: recipient (default; a car for a driver achievement, a team for a
 *   team one), driver (a driver's facts across the sessions read, for a
 *   weekend total: the driver has another car in each session) or session
 * - sum: the attribute to add up; the facts are counted without it
 * - distinct: an attribute whose different values are counted, so facts
 *   that share a value count once (a shared car of the 1950s is one place)
 * - at_least: the threshold
 */
class CountTrigger extends DeclarativeTrigger
{
    protected const OPTIONS = ['per', 'sum', 'distinct', 'at_least'];

    public function detect(SessionData $data, Collection $settled): Collection
    {
        if (isset($this->definition['sum'], $this->definition['distinct'])) {
            throw new InvalidArgumentException("The trigger of [{$this->key}] sums or counts distinct values, not both.");
        }

        $items = $this->sessions($data)->flatMap(fn (SessionData $session) => $this->selectedIn($session)->map(fn (Fact $fact) => ['fact' => $fact, 'data' => $session]));
        $groups = match ($this->definition['per'] ?? 'recipient') {
            'session' => collect([$items])->filter(fn (Collection $group) => $group->isNotEmpty()),
            'driver' => $items->groupBy(fn (array $item) => $item['data']->entry($item['fact']->entryId)?->driver_id ?? ''),
            'recipient' => $items->groupBy(fn (array $item) => $this->recipientOf($item['fact'], $item['data'])->key() ?? ''),
            default => throw new InvalidArgumentException("Unknown per for [{$this->key}]."),
        };

        return $groups
            ->map(function (Collection $group) {
                $facts = $group->pluck('fact');
                // A group stands for its first fact; a driver's weekend total for its latest, the car in the last session it appears in.
                $standsFor = ($this->definition['per'] ?? 'recipient') === 'driver' ? $group->last() : $group->first();
                $total = match (true) {
                    isset($this->definition['sum']) => $facts->sum(fn (Fact $fact) => $fact->get($this->definition['sum'])),
                    isset($this->definition['distinct']) => $facts->map(fn (Fact $fact) => $fact->get($this->definition['distinct']))->unique()->count(),
                    default => $facts->count(),
                };

                if ($total < $this->definition['at_least']) {
                    return null;
                }

                return $this->detection(
                    $standsFor['fact'],
                    $standsFor['data'],
                    ['count' => $facts->count(), 'total' => $total],
                    FactEvidence::merge($facts->map(fn (Fact $fact) => $fact->evidence)),
                    fromSeveral: true,
                );
            })
            ->filter()
            ->values();
    }
}
