<?php

namespace App\Achievements\Triggers;

use App\Achievements\Detection;
use App\Achievements\Facts\Conditions;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\Facts;
use App\Achievements\Facts\Template;
use App\Achievements\SeasonData;
use App\Achievements\SessionData;
use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\OofscoreStanding;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Type aggregate: season totals, for the season scope (Frequent Flyer, the
 * Oofies). It collects items from a source, groups them, scores each group
 * and picks the winners.
 *
 * Options:
 * - source: facts (of the season's sessions the entry lists), awards (the
 *   season's final session and weekend awards) or standings (the OOFscore
 *   after the last round)
 * - fact, where: with source facts, the fact type and its conditions
 * - shelf: with source awards, only awards on this shelf
 * - per: achievement, for one contest per achievement (source awards)
 * - group: season (everything together), driver or achievement
 * - measure: count, or an attribute summed (facts) or compared (standings: position, points)
 * - pick: all (every group), max or min
 * - at_least: the smallest value that can win
 * - ties: share (every group on the best value wins), none (a shared best
 *   gives nothing) or higher_tier (between achievements, the higher tier
 *   wins; equal tiers share)
 * - to: group (the group's driver, or nobody for season and achievement
 *   groups) or holders (every driver with an item in the winning group)
 * - instance, event, detail, note: templates with {value}, {count},
 *   {achievement} and {points}; the instance is the contest's achievement
 *   key, or "season", by default
 */
final class AggregateTrigger implements SeasonTrigger
{
    private const OPTIONS = ['type', 'source', 'fact', 'where', 'shelf', 'per', 'group', 'measure', 'pick', 'at_least', 'ties', 'to', 'instance', 'event', 'detail', 'note'];

    private const TIER_RANK = ['platinum' => 4, 'gold' => 3, 'silver' => 2, 'bronze' => 1];

    /**
     * @param  array<string, mixed>  $definition
     * @param  list<SessionType>  $sessions  The session types a facts source reads
     */
    public function __construct(
        private readonly string $key,
        private readonly array $definition,
        private readonly Facts $facts,
        private readonly Conditions $conditions,
        private readonly array $sessions,
    ) {
        $unknown = array_diff(array_keys($definition), self::OPTIONS);

        if ($unknown !== [] || ! in_array($definition['source'] ?? null, ['facts', 'awards', 'standings'], true) || ($definition['source'] === 'facts' && ! isset($definition['fact']))) {
            throw new InvalidArgumentException("The trigger of [{$this->key}] has unknown options, no known source or no fact: ".implode(', ', $unknown));
        }
    }

    public function source(): string
    {
        return $this->definition['source'];
    }

    public function factType(): ?string
    {
        return $this->definition['fact'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function predicateNames(): array
    {
        return Conditions::predicateNames($this->definition['where'] ?? []);
    }

    public function detectSeason(SeasonData $season): Collection
    {
        $items = $this->items($season);
        $contests = ($this->definition['per'] ?? null) === 'achievement'
            ? $items->groupBy(fn (array $item) => $item['achievement']->key)
            : collect(['' => $items]);

        return $contests
            ->filter(fn (Collection $contest) => $contest->isNotEmpty())
            ->flatMap(fn (Collection $contest, string $contestKey) => $this->decide($contest, $contestKey, $season))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $contest
     * @return Collection<int, Detection>
     */
    private function decide(Collection $contest, string $contestKey, SeasonData $season): Collection
    {
        $groups = $contest
            ->groupBy(fn (array $item) => match ($this->definition['group']) {
                'season' => 'season',
                'driver' => (string) $item['driver'],
                'achievement' => $item['achievement']->key,
            })
            ->map(fn (Collection $items, string $groupKey) => [
                'key' => $groupKey,
                'items' => $items,
                'value' => $this->score($items),
            ]);

        $ranked = $groups->sortBy([['value', ($this->definition['pick'] ?? 'all') === 'min' ? 'asc' : 'desc'], ['key', 'asc']])->values();

        return $this->winners($groups)->flatMap(fn (array $group) => $this->detections($group, $contestKey, $ranked, $season))->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function score(Collection $items): int|float
    {
        $measure = $this->definition['measure'] ?? 'count';

        return match (true) {
            $measure === 'count' => $items->count(),
            $this->definition['source'] === 'standings' => $items->first()[$measure],
            default => $items->sum('value'),
        };
    }

    /**
     * @param  Collection<string, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float}>  $groups
     * @return Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float}>
     */
    private function winners(Collection $groups): Collection
    {
        $pick = $this->definition['pick'] ?? 'all';
        $atLeast = $this->definition['at_least'] ?? null;
        $eligible = $groups->filter(fn (array $group) => $atLeast === null || $group['value'] >= $atLeast);

        if ($pick === 'all' || $eligible->isEmpty()) {
            return $eligible->values();
        }

        $best = $pick === 'max' ? $eligible->max('value') : $eligible->min('value');
        $winners = $eligible->filter(fn (array $group) => $group['value'] === $best)->values();

        return match ($this->definition['ties'] ?? 'share') {
            'share' => $winners,
            'none' => $winners->count() === 1 ? $winners : collect(),
            'higher_tier' => $this->highestTier($winners),
            default => throw new InvalidArgumentException("Unknown tie policy for [{$this->key}]."),
        };
    }

    /**
     * @param  Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float}>  $winners
     * @return Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float}>
     */
    private function highestTier(Collection $winners): Collection
    {
        $rank = fn (array $group) => self::TIER_RANK[$group['items']->first()['achievement']->tier?->value] ?? 0;
        $top = $winners->max($rank);

        return $winners->filter(fn (array $group) => $rank($group) === $top)->values();
    }

    /**
     * @param  array{key: string, items: Collection<int, array<string, mixed>>, value: int|float}  $group
     * @param  Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float}>  $ranked
     * @return Collection<int, Detection>
     */
    private function detections(array $group, string $contestKey, Collection $ranked, SeasonData $season): Collection
    {
        $first = $group['items']->first();
        $achievement = $first['achievement'] ?? null;
        $values = [
            'value' => $group['value'],
            'count' => $group['items']->count(),
            'achievement' => $achievement?->name,
            'points' => $first['points'] ?? null,
        ];
        $instance = $contestKey !== '' ? $contestKey : ($this->definition['group'] === 'achievement' ? $group['key'] : 'season');
        $fact = new Fact('aggregate', null, $instance, "season:{$instance}", [], FactEvidence::rows($this->evidenceSource(), []));
        $instanceKey = isset($this->definition['instance']) ? Template::render($this->definition['instance'], $fact, $values) : $instance;
        $evidence = $this->evidence($group, $ranked, $season, Template::render($this->definition['note'] ?? null, $fact, $values));

        $recipients = match ($this->definition['to'] ?? 'group') {
            'holders' => $group['items']->pluck('driver')->filter()->unique()->sort()->values(),
            default => collect([$this->definition['group'] === 'driver' ? (int) $group['key'] : null]),
        };

        return $recipients->map(fn (?int $driverId) => new Detection(
            achievementKey: $this->key,
            driverEntryId: $driverId === null ? null : ($season->entryOfDriver($driverId)?->id ?? $group['items']->where('driver', $driverId)->last()['entry'] ?? null),
            instanceKey: $instanceKey,
            eventKey: isset($this->definition['event']) ? Template::render($this->definition['event'], $fact, $values) : "season:{$instanceKey}",
            evidence: $evidence,
            detail: Template::render($this->definition['detail'] ?? null, $fact, $values),
        ));
    }

    /**
     * Facts: the winning group's total per session. Awards and standings:
     * the five best groups of the contest, so the evidence shows the race.
     *
     * @param  array{key: string, items: Collection<int, array<string, mixed>>, value: int|float}  $group
     * @param  Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float}>  $ranked
     * @return array{source: string, rows?: list<array<string, mixed>>, note?: string}
     */
    private function evidence(array $group, Collection $ranked, SeasonData $season, ?string $note): array
    {
        if ($this->definition['source'] === 'facts') {
            $rows = $group['items']->groupBy('session')
                ->map(fn (Collection $items, string $session) => ['session' => $session, 'count' => ($this->definition['measure'] ?? 'count') === 'count' ? $items->count() : $items->sum('value')])
                ->values()
                ->all();

            return FactEvidence::rows($this->evidenceSource(), $rows, $note)->render();
        }

        $rows = $ranked->take(5)->map(function (array $other) use ($season) {
            $first = $other['items']->first();

            return match ($this->definition['group']) {
                'achievement' => ['achievement' => $first['achievement']->name, 'count' => $other['value']],
                default => array_filter([
                    'driver' => $season->entryOfDriver((int) $other['key'])?->driver->family_name ?? $first['name'] ?? null,
                    'count' => $this->definition['source'] === 'awards' ? $other['value'] : null,
                    'position' => $first['position'] ?? null,
                    'points' => $first['points'] ?? null,
                ], fn ($value) => $value !== null),
            };
        })->values()->all();

        return FactEvidence::rows($this->evidenceSource(), $rows, $note)->render();
    }

    private function evidenceSource(): string
    {
        return match ($this->definition['source']) {
            'facts' => $this->facts->provider($this->definition['fact'])->sources()[0]->value === 'openf1_race_control' ? 'race_control' : 'openf1',
            'awards' => 'awards',
            'standings' => 'oofscore',
        };
    }

    /**
     * What the source gives for the season, one item per fact, award or standings row.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(SeasonData $season): Collection
    {
        return match ($this->definition['source']) {
            'facts' => $season->sessions($this->sessions)->flatMap(fn (SessionData $data) => $this->facts->of($data, $this->definition['fact'])
                ->filter(fn (Fact $fact) => $this->conditions->pass($this->definition['where'] ?? [], $fact, $data))
                ->map(fn (Fact $fact) => [
                    'driver' => $data->entry($fact->entryId)?->driver_id,
                    'entry' => $fact->entryId,
                    'value' => ($this->definition['measure'] ?? 'count') === 'count' ? 1 : $fact->get($this->definition['measure']),
                    'session' => "Round {$data->session->round->round_number} {$data->type()->label()}",
                ]))->values(),
            'awards' => $season->finalAwards()
                ->filter(fn (AchievementAward $award) => $award->driver_id !== null && (! isset($this->definition['shelf']) || $award->shelf?->value === $this->definition['shelf']))
                ->map(fn (AchievementAward $award) => [
                    'driver' => $award->driver_id,
                    'entry' => $award->driver_entry_id,
                    'achievement' => $award->achievement,
                    'name' => $award->driver?->family_name,
                    'value' => 1,
                ])->values(),
            'standings' => $season->finalStandings()->map(fn (OofscoreStanding $row) => [
                'driver' => $row->driver_id,
                'entry' => null,
                'name' => $row->driver?->family_name,
                'position' => $row->position,
                'points' => self::number($row->points),
                'value' => 1,
            ])->values(),
        };
    }

    private static function number(float $points): int|float
    {
        return floor($points) === $points ? (int) $points : $points;
    }
}
