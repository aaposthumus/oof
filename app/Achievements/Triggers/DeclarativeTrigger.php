<?php

namespace App\Achievements\Triggers;

use App\Achievements\Detection;
use App\Achievements\Facts\Conditions;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\Facts;
use App\Achievements\Facts\Template;
use App\Achievements\SessionData;
use App\Enums\AwardConfidence;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * A trigger written as catalogue data: a selection over one type of fact.
 *
 * Options every declarative type takes:
 * - fact: the fact type to read
 * - where: conditions every fact must meet (see Conditions)
 * - instance, event: key templates; the fact's own keys by default
 * - detail, note: text templates (see Template); the note replaces the fact's own
 * - confidence: high, medium or low; the fact's own by default
 * - confidence_when: [level => where], the first level whose conditions hold
 */
abstract class DeclarativeTrigger implements Trigger
{
    private const COMMON_OPTIONS = ['type', 'fact', 'where', 'instance', 'event', 'detail', 'note', 'confidence', 'confidence_when'];

    /**
     * Options of this trigger type besides the common ones.
     *
     * @var list<string>
     */
    protected const OPTIONS = [];

    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        protected readonly string $key,
        protected readonly array $definition,
        protected readonly Facts $facts,
        protected readonly Conditions $conditions,
    ) {
        $unknown = array_diff(array_keys($definition), [...self::COMMON_OPTIONS, ...static::OPTIONS]);

        if ($unknown !== [] || ! isset($definition['fact'])) {
            throw new InvalidArgumentException("The trigger of [{$key}] has unknown options or no fact: ".implode(', ', $unknown));
        }
    }

    public function isLate(): bool
    {
        return false;
    }

    public function factType(): string
    {
        return $this->definition['fact'];
    }

    /**
     * @return list<string>
     */
    public function predicateNames(): array
    {
        $where = [$this->definition['where'] ?? [], ...array_values($this->definition['confidence_when'] ?? [])];

        return array_values(array_unique(array_merge(...array_map(Conditions::predicateNames(...), $where))));
    }

    /**
     * The session's facts of the type that meet the conditions.
     *
     * @return Collection<int, Fact>
     */
    protected function selected(SessionData $data): Collection
    {
        $where = $this->definition['where'] ?? [];

        return $this->facts->of($data, $this->definition['fact'])
            ->filter(fn (Fact $fact) => $this->conditions->pass($where, $fact, $data))
            ->values();
    }

    /**
     * A detection for one fact, or for a group of facts it represents.
     *
     * @param  array<string, mixed>  $values  Computed values for the templates
     * @param  bool  $fromSeveral  Whether it rests on several facts, so on no single message naming cars
     */
    protected function detection(Fact $fact, SessionData $data, array $values = [], ?FactEvidence $evidence = null, bool $fromSeveral = false): Detection
    {
        $evidence ??= $fact->evidence;

        if (isset($this->definition['note'])) {
            $evidence = $evidence->withNote(Template::render($this->definition['note'], $fact, $values));
        }

        return new Detection(
            achievementKey: $this->key,
            driverEntryId: $fact->entryId,
            instanceKey: isset($this->definition['instance']) ? Template::render($this->definition['instance'], $fact, $values) : $fact->instanceKey,
            eventKey: isset($this->definition['event']) ? Template::render($this->definition['event'], $fact, $values) : $fact->eventKey,
            evidence: $evidence->render(),
            confidence: $this->confidence($fact, $data),
            detail: Template::render($this->definition['detail'] ?? null, $fact, $values),
            reviewReason: $fact->reviewReason,
            isOpen: $fact->isOpen,
            carsNamed: $fromSeveral ? null : $fact->carsNamed,
        );
    }

    private function confidence(Fact $fact, SessionData $data): AwardConfidence
    {
        foreach ($this->definition['confidence_when'] ?? [] as $level => $where) {
            if ($this->conditions->pass($where, $fact, $data)) {
                return AwardConfidence::from($level);
            }
        }

        return isset($this->definition['confidence']) ? AwardConfidence::from($this->definition['confidence']) : $fact->confidence;
    }
}
