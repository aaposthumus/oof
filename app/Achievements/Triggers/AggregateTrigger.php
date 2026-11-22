<?php

namespace App\Achievements\Triggers;

use App\Achievements\Detection;
use App\Achievements\Facts\Conditions;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\Facts;
use App\Achievements\Facts\MeasuresCoverage;
use App\Achievements\Facts\Template;
use App\Achievements\Recipient;
use App\Achievements\Rules\Sensitivity;
use App\Achievements\SeasonData;
use App\Achievements\SessionData;
use App\Achievements\Support\PitStops;
use App\Achievements\Support\TeamForm;
use App\Enums\RecipientType;
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
 * - source: facts (of the season's sessions the entry lists, the
 *   Indianapolis 500 left out), awards (the season's final session and
 *   weekend awards), standings (the OOFscore after the last round) or
 *   championship (the final drivers' standings, each driver with
 *   attributes position, points, wins and podiums: Grand Prix podiums)
 * - fact, where: with source facts, the fact type and its conditions; with
 *   source championship, conditions on its attributes
 * - shelf: with source awards, only awards on this shelf
 * - except: with source awards, achievement keys left out
 * - per: achievement, for one contest per achievement (source awards)
 * - group: season (everything together), driver, team (the season team of
 *   the fact's car; the entry's recipient must be team) or achievement
 * - measure: count, or an attribute (facts: reduced per group; standings
 *   and championship: compared, e.g. position or points)
 * - reduce: with source facts and an attribute measure, how a group's
 *   values become one: sum (default), min or max (one stop as the measure)
 * - pick: all (every group), max or min
 * - at_least: the smallest value that can win
 * - tie_break: with source facts, an attribute summed per group that
 *   decides between groups level on the measure, in the direction of pick
 *   (Best in Show: races finished lapped, then laps down); groups level on
 *   both are left to the tie policy
 * - ties: share (every group on the best value wins), none (a shared best
 *   gives nothing) or higher_tier (between achievements, the higher tier
 *   wins; equal tiers share)
 * - in_every: with source facts, a session type: only a group with an item
 *   in every session of that type in the season can win (Perfect
 *   Attendance: points in every Grand Prix)
 * - skip_sensitive_rounds: with source facts, true to leave out the
 *   sessions of sensitive rounds (Homebody does not count a retirement the
 *   weekend's own award is withheld for)
 * - coverage: with source facts, [in => session type, at_least => share,
 *   season_at_least => share, detail => template] (see MeasuresCoverage): a
 *   session of that type measured below at_least is left out entirely, for
 *   every group; the season gives nothing unless at least season_at_least of
 *   those sessions pass (1, every one, by default); the detail template,
 *   with {left_out} the number left out, is added to the award's detail
 *   when any were (the pit stop awards)
 * - value_format: seconds, to show a value in milliseconds as "2.1 s" in
 *   the templates (the pit stop awards)
 * - to: group (the group's driver or team, or nobody for season and
 *   achievement groups) or holders (every driver with an item in the
 *   winning group)
 * - instance, event, detail, note: templates with {value}, {count},
 *   {achievement} and {points}; the instance is the contest's achievement
 *   key, or "season", by default
 */
final class AggregateTrigger implements SeasonTrigger
{
    private const OPTIONS = ['type', 'source', 'fact', 'where', 'shelf', 'except', 'per', 'group', 'measure', 'reduce', 'pick', 'at_least', 'tie_break', 'ties', 'in_every', 'skip_sensitive_rounds', 'coverage', 'value_format', 'to', 'instance', 'event', 'detail', 'note'];

    private const FACT_ONLY_OPTIONS = ['reduce', 'tie_break', 'in_every', 'skip_sensitive_rounds', 'coverage', 'value_format'];

    private const TIER_RANK = ['platinum' => 4, 'gold' => 3, 'silver' => 2, 'bronze' => 1];

    /**
     * The sessions the coverage option leaves out of the season being decided.
     *
     * @var Collection<int, SessionData>
     */
    private Collection $leftOut;

    /**
     * @param  array<string, mixed>  $definition
     * @param  list<SessionType>  $sessions  The session types a facts source reads
     * @param  RecipientType  $recipient  A driver (by the group or holders), a team (by the group) or the Safety Car
     */
    public function __construct(
        private readonly string $key,
        private readonly array $definition,
        private readonly Facts $facts,
        private readonly Conditions $conditions,
        private readonly array $sessions,
        private readonly RecipientType $recipient = RecipientType::Driver,
    ) {
        $unknown = array_diff(array_keys($definition), self::OPTIONS);

        if ($unknown !== [] || ! in_array($definition['source'] ?? null, ['facts', 'awards', 'standings', 'championship'], true) || ($definition['source'] === 'facts' && ! isset($definition['fact']))) {
            throw new InvalidArgumentException("The trigger of [{$this->key}] has unknown options, no known source or no fact: ".implode(', ', $unknown));
        }

        if ($definition['source'] !== 'facts' && array_intersect(self::FACT_ONLY_OPTIONS, array_keys($definition)) !== []) {
            throw new InvalidArgumentException("The trigger of [{$this->key}] uses options that only a facts source takes.");
        }

        if (($recipient === RecipientType::Team) !== (($definition['group'] ?? null) === 'team') || (($definition['group'] ?? null) === 'team' && $definition['source'] !== 'facts')) {
            throw new InvalidArgumentException("[{$this->key}]: a team award is grouped by team, over facts, and a team group goes to a team.");
        }

        if (! in_array($definition['reduce'] ?? 'sum', ['sum', 'min', 'max'], true)) {
            throw new InvalidArgumentException("Unknown reduce for [{$this->key}].");
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
        $this->leftOut = collect();

        if (! $this->isCovered($season)) {
            return collect();
        }

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
     * Whether the season is measured well enough for the coverage option:
     * the sessions of its type measured below the bar are left out (kept in
     * leftOut), and enough of them must pass. A session with nothing to
     * measure passes.
     */
    private function isCovered(SeasonData $season): bool
    {
        if (! isset($this->definition['coverage'])) {
            return true;
        }

        $provider = $this->facts->provider($this->definition['fact']);

        if (! $provider instanceof MeasuresCoverage) {
            throw new InvalidArgumentException("The facts of [{$this->key}] do not measure their coverage.");
        }

        $coverage = $this->definition['coverage'];
        $type = SessionType::from($coverage['in']);
        $judged = $this->readSessions($season)->filter(fn (SessionData $data) => $data->type() === $type)->values();
        $this->leftOut = $judged->filter(fn (SessionData $data) => ($provider->coverage($data) ?? 1.0) < $coverage['at_least'])->values();

        return $judged->isEmpty() || ($judged->count() - $this->leftOut->count()) / $judged->count() >= ($coverage['season_at_least'] ?? 1.0);
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
                'team' => (string) $item['team'],
                'achievement' => $item['achievement']->key,
            })
            ->map(fn (Collection $items, string $groupKey) => [
                'key' => $groupKey,
                'items' => $items,
                'value' => $this->score($items),
                'tie' => isset($this->definition['tie_break']) ? $items->sum('tie') : 0,
            ])
            ->filter(fn (array $group) => $this->attendedEvery($group['items'], $season));

        $ranked = $groups->sortBy([['value', ($this->definition['pick'] ?? 'all') === 'min' ? 'asc' : 'desc'], ['key', 'asc']])->values();

        return $this->winners($groups)->flatMap(fn (array $group) => $this->detections($group, $contestKey, $ranked, $season))->values();
    }

    /**
     * With in_every: whether the group has an item in every session of that type.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function attendedEvery(Collection $items, SeasonData $season): bool
    {
        if (! isset($this->definition['in_every'])) {
            return true;
        }

        $type = SessionType::from($this->definition['in_every']);
        $required = $this->seasonSessions($season)->filter(fn (SessionData $data) => $data->type() === $type)->map(fn (SessionData $data) => $data->session->id);

        return $required->isNotEmpty() && $required->diff($items->pluck('session_id')->unique())->isEmpty();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function score(Collection $items): int|float
    {
        $measure = $this->definition['measure'] ?? 'count';

        return match (true) {
            $measure === 'count' => $items->count(),
            in_array($this->definition['source'], ['standings', 'championship'], true) => $items->first()[$measure],
            default => match ($this->definition['reduce'] ?? 'sum') {
                'min' => $items->min('value'),
                'max' => $items->max('value'),
                default => $items->sum('value'),
            },
        };
    }

    /**
     * @param  Collection<string, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float, tie: int|float}>  $groups
     * @return Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float, tie: int|float}>
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

        if (isset($this->definition['tie_break']) && $winners->count() > 1) {
            $bestTie = $pick === 'max' ? $winners->max('tie') : $winners->min('tie');
            $winners = $winners->filter(fn (array $group) => $group['tie'] === $bestTie)->values();
        }

        return match ($this->definition['ties'] ?? 'share') {
            'share' => $winners,
            'none' => $winners->count() === 1 ? $winners : collect(),
            'higher_tier' => $this->highestTier($winners),
            default => throw new InvalidArgumentException("Unknown tie policy for [{$this->key}]."),
        };
    }

    /**
     * @param  Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float, tie: int|float}>  $winners
     * @return Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float, tie: int|float}>
     */
    private function highestTier(Collection $winners): Collection
    {
        $rank = fn (array $group) => self::TIER_RANK[$group['items']->first()['achievement']->tier?->value] ?? 0;
        $top = $winners->max($rank);

        return $winners->filter(fn (array $group) => $rank($group) === $top)->values();
    }

    /**
     * @param  array{key: string, items: Collection<int, array<string, mixed>>, value: int|float, tie: int|float}  $group
     * @param  Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float, tie: int|float}>  $ranked
     * @return Collection<int, Detection>
     */
    private function detections(array $group, string $contestKey, Collection $ranked, SeasonData $season): Collection
    {
        $first = $group['items']->first();
        $achievement = $first['achievement'] ?? null;
        $values = [
            'value' => ($this->definition['value_format'] ?? null) === 'seconds' ? PitStops::seconds((int) $group['value']) : $group['value'],
            'count' => $group['items']->count(),
            'achievement' => $achievement?->name,
            'points' => $first['points'] ?? null,
        ];
        $instance = $contestKey !== '' ? $contestKey : ($this->definition['group'] === 'achievement' ? $group['key'] : 'season');
        $fact = new Fact('aggregate', null, $instance, "season:{$instance}", [], FactEvidence::rows($this->evidenceSource(), []));
        $instanceKey = isset($this->definition['instance']) ? Template::render($this->definition['instance'], $fact, $values) : $instance;
        $note = Template::render($this->definition['note'] ?? null, $fact, $values);
        $detail = Template::render($this->definition['detail'] ?? null, $fact, $values);

        if ($this->leftOut->isNotEmpty()) {
            $sessions = $this->leftOut->map(fn (SessionData $data) => "Round {$data->session->round->round_number} {$data->type()->label()}")->implode(', ');
            $note = trim(($note ?? '').' Left out without full timing data: '.$sessions.'.');
            $leftOut = Template::render($this->definition['coverage']['detail'] ?? null, $fact, ['left_out' => $this->leftOut->count()]);
            $detail = $leftOut === null ? $detail : implode(' · ', array_filter([$detail, $leftOut]));
        }

        $evidence = $this->evidence($group, $ranked, $season, $note);
        $detection = fn (Recipient $recipient) => new Detection(
            achievementKey: $this->key,
            recipient: $recipient,
            instanceKey: $instanceKey,
            eventKey: isset($this->definition['event']) ? Template::render($this->definition['event'], $fact, $values) : "season:{$instanceKey}",
            evidence: $evidence,
            detail: $detail,
        );

        if ($this->recipient === RecipientType::Team) {
            return collect([$detection(Recipient::team((int) $group['key']))]);
        }

        $recipients = match ($this->definition['to'] ?? 'group') {
            'holders' => $group['items']->pluck('driver')->filter()->unique()->sort()->values(),
            default => collect([$this->definition['group'] === 'driver' ? (int) $group['key'] : null]),
        };

        return $recipients->map(fn (?int $driverId) => $detection($this->recipient === RecipientType::SafetyCar
            ? Recipient::safetyCar()
            : Recipient::driver($driverId === null ? null : ($season->entryOfDriver($driverId)?->id ?? $group['items']->where('driver', $driverId)->last()['entry'] ?? null))));
    }

    /**
     * Facts: the winning group's value per session. Awards, standings and
     * the championship: the five best groups of the contest, so the
     * evidence shows the race.
     *
     * @param  array{key: string, items: Collection<int, array<string, mixed>>, value: int|float, tie: int|float}  $group
     * @param  Collection<int, array{key: string, items: Collection<int, array<string, mixed>>, value: int|float, tie: int|float}>  $ranked
     * @return array{source: string, rows?: list<array<string, mixed>>, note?: string}
     */
    private function evidence(array $group, Collection $ranked, SeasonData $season, ?string $note): array
    {
        if ($this->definition['source'] === 'facts') {
            $rows = $group['items']->groupBy('session')
                ->map(fn (Collection $items, string $session) => ['session' => $session, 'count' => ($this->definition['measure'] ?? 'count') === 'count' ? $items->count() : $this->score($items)])
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
            'championship' => 'jolpica',
        };
    }

    /**
     * The season's sessions a facts source reads, without those the coverage
     * option left out.
     *
     * @return Collection<int, SessionData>
     */
    private function seasonSessions(SeasonData $season): Collection
    {
        $leftOut = $this->leftOut->map(fn (SessionData $data) => $data->session->id)->all();

        return $this->readSessions($season)->reject(fn (SessionData $data) => in_array($data->session->id, $leftOut, true))->values();
    }

    /**
     * The sessions of the types the entry lists, without the Indianapolis
     * 500 (no season award counts it) and, with skip_sensitive_rounds,
     * without the sessions of sensitive rounds.
     *
     * @return Collection<int, SessionData>
     */
    private function readSessions(SeasonData $season): Collection
    {
        return $season->sessions($this->sessions)
            ->reject(fn (SessionData $data) => TeamForm::isIndianapolis500($data->session->round))
            ->reject(fn (SessionData $data) => ($this->definition['skip_sensitive_rounds'] ?? false) && Sensitivity::forRound($data->session->round_id)['sensitive'])
            ->values();
    }

    /**
     * What the source gives for the season, one item per fact, award or standings row.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(SeasonData $season): Collection
    {
        return match ($this->definition['source']) {
            'facts' => $this->seasonSessions($season)->flatMap(fn (SessionData $data) => $this->facts->of($data, $this->definition['fact'])
                ->filter(fn (Fact $fact) => $this->conditions->pass($this->definition['where'] ?? [], $fact, $data))
                ->filter(fn (Fact $fact) => $this->recipient !== RecipientType::Team || $data->entry($fact->entryId)?->season_team_id !== null)
                ->map(fn (Fact $fact) => [
                    'driver' => $data->entry($fact->entryId)?->driver_id,
                    'team' => $data->entry($fact->entryId)?->season_team_id,
                    'entry' => $fact->entryId,
                    'value' => ($this->definition['measure'] ?? 'count') === 'count' ? 1 : $fact->get($this->definition['measure']),
                    'tie' => isset($this->definition['tie_break']) ? $fact->get($this->definition['tie_break']) : 0,
                    'session_id' => $data->session->id,
                    'session' => "Round {$data->session->round->round_number} {$data->type()->label()}",
                ]))->values(),
            'awards' => $season->finalAwards()
                ->filter(fn (AchievementAward $award) => $award->driver_id !== null
                    && (! isset($this->definition['shelf']) || $award->shelf?->value === $this->definition['shelf'])
                    && ! in_array($award->achievement->key, $this->definition['except'] ?? [], true))
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
            'championship' => $this->championship($season),
        };
    }

    /**
     * The final drivers' standings, as items whose attributes the where
     * conditions read (position, points, wins, podiums).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function championship(SeasonData $season): Collection
    {
        // Predicates in the conditions read the final race; attributes come from the row.
        $data = $season->sessions([SessionType::Race])->last();

        if ($data === null) {
            return collect();
        }

        return $season->championship()
            ->map(fn (array $row) => [...$row, 'entry' => $season->entryOfDriver($row['driver'])?->id, 'value' => 1])
            ->filter(fn (array $row) => $this->conditions->pass($this->definition['where'] ?? [], new Fact('championship', $row['entry'], 'season', 'season', array_intersect_key($row, array_flip(['position', 'points', 'wins', 'podiums'])), FactEvidence::rows('jolpica', [])), $data))
            ->values();
    }

    private static function number(float $points): int|float
    {
        return floor($points) === $points ? (int) $points : $points;
    }
}
