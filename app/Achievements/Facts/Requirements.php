<?php

namespace App\Achievements\Facts;

use App\Achievements\Catalogue;
use App\Achievements\SessionData;
use Illuminate\Contracts\Container\Container;

/**
 * The data each achievement needs, derived from its trigger: the fact
 * provider (or custom class), the predicates and the attributes its
 * conditions read. The engine skips an achievement in a session before its
 * first year, or when the session has no rows in a source its facts are
 * built from.
 *
 * @phpstan-type Needs array{sources: list<DataSource>, available_from: int, notes: list<string>, session_sources: list<DataSource>}
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

    public function allows(string $key, SessionData $data): bool
    {
        $needs = $this->for($key);

        return $data->year() >= $needs['available_from']
            && collect($needs['session_sources'])->every(fn (DataSource $source) => $source->hasRows($data));
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
            'session_sources' => $provider->sources(),
        ];
    }
}
