<?php

namespace App\Achievements\Triggers;

use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\SessionData;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Type extreme: the one candidate with the most or least of something. A
 * candidate is a fact; with measure "count" a car and all its facts; with
 * per "driver" a driver and all their facts across the sessions read (the
 * measure summed, or the facts counted). Templates get {value} (the
 * measure) and {count} (the facts).
 *
 * Options besides the common ones:
 * - measure: the attribute to compare, or count
 * - pick: max or min
 * - at_least: the smallest value that can win (max only)
 * - ties: none (a shared extreme gives nothing; the only policy so far)
 * - among: race_drivers, to leave out practice-only drivers and FP1 rookies
 * - per: driver, to total a driver's facts over the weekend's sessions
 * - evidence: fact (default: the winner's) or leaderboard (the top five values)
 */
class ExtremeTrigger extends DeclarativeTrigger
{
    protected const OPTIONS = ['measure', 'pick', 'at_least', 'ties', 'among', 'per', 'evidence'];

    public function detect(SessionData $data, Collection $settled): Collection
    {
        $grouped = $this->definition['measure'] === 'count' || ($this->definition['per'] ?? null) === 'driver';
        $candidates = $this->candidates($data);
        $eligible = ($this->definition['among'] ?? null) === 'race_drivers'
            ? $candidates->filter(fn (array $candidate) => $candidate['data']->raceDriverEntries()->has($candidate['fact']->entryId))
            : $candidates;

        $best = $this->definition['pick'] === 'max' ? $eligible->max('value') : $eligible->min('value');
        $winners = $eligible->filter(fn (array $candidate) => $candidate['value'] === $best);

        if ($best === null || $winners->count() !== 1 || (isset($this->definition['at_least']) && $best < $this->definition['at_least'])) {
            return collect();
        }

        $winner = $winners->first();
        $evidence = ($this->definition['evidence'] ?? 'fact') === 'leaderboard' ? $this->leaderboard($candidates, $data) : $winner['evidence'];

        return collect([$this->detection($winner['fact'], $data, ['value' => $best, 'count' => $winner['count']], $evidence, fromSeveral: $grouped)]);
    }

    /**
     * @return Collection<int, array{fact: Fact, value: mixed, count: int, evidence: FactEvidence, data: SessionData}>
     */
    private function candidates(SessionData $data): Collection
    {
        $facts = $this->sessions($data)->flatMap(fn (SessionData $session) => $this->selectedIn($session)->map(fn (Fact $fact) => ['fact' => $fact, 'data' => $session]));
        $byCount = $this->definition['measure'] === 'count';

        $groups = match (true) {
            ($this->definition['per'] ?? null) === 'driver' => $facts->groupBy(fn (array $item) => $item['data']->entry($item['fact']->entryId)?->driver_id),
            $byCount => $facts->groupBy(fn (array $item) => $item['fact']->entryId),
            default => null,
        };

        if ($groups === null) {
            return $facts->map(fn (array $item) => [
                'fact' => $item['fact'],
                'value' => $item['fact']->get($this->definition['measure']),
                'count' => 1,
                'evidence' => $item['fact']->evidence,
                'data' => $item['data'],
            ])->values();
        }

        // A group stands for its latest fact: the car in the last session it appears in.
        return $groups->map(fn (Collection $group) => [
            'fact' => $group->last()['fact'],
            'value' => $byCount ? $group->count() : $group->sum(fn (array $item) => $item['fact']->get($this->definition['measure'])),
            'count' => $group->count(),
            'evidence' => FactEvidence::merge($group->map(fn (array $item) => $item['fact']->evidence)),
            'data' => $group->last()['data'],
        ])->values();
    }

    /**
     * The five highest values, of every candidate. Equal values go by entry
     * ID, so every run lists the same five in the same order.
     *
     * @param  Collection<int, array{fact: Fact, value: mixed}>  $candidates
     */
    private function leaderboard(Collection $candidates, SessionData $data): FactEvidence
    {
        if ($this->definition['pick'] !== 'max') {
            throw new InvalidArgumentException("The leaderboard evidence of [{$this->key}] needs pick max.");
        }

        $rows = $candidates
            ->map(fn (array $candidate) => ['id' => $candidate['fact']->entryId, 'count' => $candidate['value']])
            ->sortBy([['count', 'desc'], ['id', 'asc']])
            ->take(5)
            ->map(fn (array $row) => ['driver' => $data->entry($row['id'])?->driver->family_name, 'count' => $row['count']])
            ->values()
            ->all();

        return FactEvidence::rows('openf1', $rows);
    }
}
