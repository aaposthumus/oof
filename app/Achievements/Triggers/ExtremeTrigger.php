<?php

namespace App\Achievements\Triggers;

use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\SessionData;
use App\Enums\SessionType;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Type extreme: the one candidate with the most or least of something. A
 * candidate is a fact, or with measure "count" a car and all its facts.
 * Templates get {value} (the measure) and {count} (the facts).
 *
 * Options besides the common ones:
 * - measure: the attribute to compare, or count
 * - pick: max or min
 * - at_least: the smallest value that can win (max only)
 * - ties: none (a shared extreme gives nothing; the only policy so far)
 * - among: race_drivers, to leave out practice-only drivers and FP1 rookies
 * - from: session types of the round to take facts from, instead of this session
 * - only_own_session: with from, award only when the winning fact is from this session
 * - evidence: fact (default: the winner's) or leaderboard (the top five values)
 */
class ExtremeTrigger extends DeclarativeTrigger
{
    protected const OPTIONS = ['measure', 'pick', 'at_least', 'ties', 'among', 'from', 'only_own_session', 'evidence'];

    public function detect(SessionData $data, Collection $settled): Collection
    {
        $byCount = $this->definition['measure'] === 'count';
        $candidates = $this->candidates($data, $byCount);
        $eligible = ($this->definition['among'] ?? null) === 'race_drivers'
            ? $candidates->filter(fn (array $candidate) => $data->raceDriverEntries()->has($candidate['fact']->entryId))
            : $candidates;

        $best = $this->definition['pick'] === 'max' ? $eligible->max('value') : $eligible->min('value');
        $winners = $eligible->filter(fn (array $candidate) => $candidate['value'] === $best);

        if ($best === null || $winners->count() !== 1 || (isset($this->definition['at_least']) && $best < $this->definition['at_least'])) {
            return collect();
        }

        $winner = $winners->first();

        if (($this->definition['only_own_session'] ?? false) && $winner['session'] !== $data->session->id) {
            return collect();
        }

        $evidence = ($this->definition['evidence'] ?? 'fact') === 'leaderboard' ? $this->leaderboard($candidates, $data) : $winner['evidence'];

        return collect([$this->detection($winner['fact'], $data, ['value' => $best, 'count' => $winner['count']], $evidence, fromSeveral: $byCount)]);
    }

    /**
     * @return Collection<int, array{fact: Fact, value: mixed, count: int, evidence: FactEvidence, session: int}>
     */
    private function candidates(SessionData $data, bool $byCount): Collection
    {
        $sessions = isset($this->definition['from'])
            ? collect($this->definition['from'])->map(fn (string $type) => $data->roundSession(SessionType::from($type)))->filter()
            : collect([$data]);

        return $sessions->flatMap(function (SessionData $session) use ($byCount) {
            $facts = $this->selected($session);

            if (! $byCount) {
                return $facts->map(fn (Fact $fact) => [
                    'fact' => $fact,
                    'value' => $fact->get($this->definition['measure']),
                    'count' => 1,
                    'evidence' => $fact->evidence,
                    'session' => $session->session->id,
                ]);
            }

            return $facts->groupBy(fn (Fact $fact) => $fact->entryId)->map(fn (Collection $group) => [
                'fact' => $group->first(),
                'value' => $group->count(),
                'count' => $group->count(),
                'evidence' => FactEvidence::merge($group->map(fn (Fact $fact) => $fact->evidence)),
                'session' => $session->session->id,
            ]);
        })->values();
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
