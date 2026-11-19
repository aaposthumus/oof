<?php

namespace App\Achievements\Facts;

use App\Achievements\Catalogue;
use App\Achievements\SessionData;
use App\Achievements\Support\TeamForm;
use App\Enums\AchievementShelf;
use Illuminate\Contracts\Container\Container;

/**
 * The data each achievement needs, derived from its trigger: the fact
 * provider (or custom class), the predicates and the attributes its
 * conditions read. The engine skips an achievement in a session before its
 * first year, or when the session has no rows in a source its facts are
 * built from.
 *
 * Reads earlier races: whether a predicate or provider reads races before
 * the round (ReadsEarlierRaces), for --stale.
 *
 * @phpstan-type Needs array{sources: list<DataSource>, available_from: int, notes: list<string>, session_sources: list<DataSource>, reads_earlier_races: bool}
 */
class Requirements
{
    /**
     * @var array<string, Needs>
     */
    private array $derived = [];

    public function __construct(
        private Catalogue $catalogue,
        private Facts $facts,
        private Predicates $predicates,
        private Container $container,
    ) {}

    /**
     * @return Needs
     */
    public function for(string $key): array
    {
        return $this->derived[$key] ??= $this->derive($this->catalogue->trigger($key));
    }

    /**
     * Whether to look for it in a session, or in a weekend built from these
     * sessions: the year is late enough, each source its facts come from has
     * rows in the session (for a weekend, in at least one of them), and on an
     * Indianapolis 500 round it is one of the few achievements given there.
     *
     * @param  iterable<SessionData>|null  $sessions  The weekend's sessions it reads; the session itself by default
     */
    public function allows(string $key, SessionData $data, ?iterable $sessions = null): bool
    {
        $needs = $this->for($key);
        $sessions = collect($sessions ?? [$data]);

        // The Indianapolis 500 of 1950 to 1960 gives only the glory that restates the official record.
        if (TeamForm::isIndianapolis500($data->session->round) && ! in_array($key, config('achievements.indianapolis_500_achievements'), true)) {
            return false;
        }

        return $data->year() >= $needs['available_from']
            && collect($needs['session_sources'])->every(fn (DataSource $source) => $sessions->contains(fn (SessionData $session) => $source->hasRows($session)));
    }

    public function allowsSeason(string $key, int $year): bool
    {
        return $year >= $this->for($key)['available_from'];
    }

    /**
     * @param  array<string, mixed>  $trigger
     * @return Needs
     */
    private function derive(array $trigger): array
    {
        if ($trigger['type'] === 'custom') {
            $class = $this->container->make($trigger['class']);

            return [
                'sources' => $class->sources(),
                'available_from' => $class->availableFrom(),
                'notes' => $class->notes(),
                'session_sources' => $class->sources(),
                'reads_earlier_races' => $class instanceof ReadsEarlierRaces,
            ];
        }

        if ($trigger['type'] === 'manual') {
            return [
                'sources' => [],
                'available_from' => (int) config('achievements.first_season'),
                'notes' => ['Awarded by hand in the CMS only, never by the engine.'],
                'session_sources' => [],
                'reads_earlier_races' => false,
            ];
        }

        if ($trigger['type'] === 'aggregate' && $trigger['source'] !== 'facts') {
            return [
                'sources' => [$trigger['source'] === 'awards' ? DataSource::Awards : DataSource::OofScore],
                'available_from' => AchievementShelf::Oofies->firstSeason(),
                'notes' => [$trigger['source'] === 'awards' ? 'Reads the season\'s final awards, once its final round is final.' : 'Reads the OOFscore standings after the season\'s last round, once it is final.'],
                'session_sources' => [],
                'reads_earlier_races' => false,
            ];
        }

        $provider = $this->facts->provider($trigger['fact']);
        $where = [$trigger['where'] ?? [], ...array_values($trigger['confidence_when'] ?? [])];
        $needs = [$provider, ...array_map(fn (string $name) => $this->predicates->get($name), Conditions::predicateNames(array_merge(...$where)))];
        $attributes = array_flip(Conditions::attributeNames(array_merge(...$where)));
        $attributeYears = $provider instanceof DescribesAttributes ? array_intersect_key($provider->attributeYears(), $attributes) : [];
        $attributeNotes = $provider instanceof DescribesAttributes ? array_intersect_key($provider->attributeNotes(), $attributes) : [];

        return [
            'sources' => collect($needs)->flatMap(fn (DataNeeds $need) => $need->sources())->unique(fn (DataSource $source) => $source->value)->values()->all(),
            'available_from' => max([...array_map(fn (DataNeeds $need) => $need->availableFrom(), $needs), ...array_values($attributeYears)]),
            'notes' => collect($needs)->flatMap(fn (DataNeeds $need) => $need->notes())->concat(array_values($attributeNotes))->unique()->values()->all(),
            // A season total is not gated per session.
            'session_sources' => match (true) {
                $trigger['type'] === 'aggregate' => [],
                $provider instanceof GatesSessions => $provider->sessionSources(),
                default => $provider->sources(),
            },
            'reads_earlier_races' => collect($needs)->contains(fn (DataNeeds $need) => $need instanceof ReadsEarlierRaces),
        ];
    }
}
