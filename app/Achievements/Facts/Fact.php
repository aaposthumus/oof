<?php

namespace App\Achievements\Facts;

use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use Closure;
use InvalidArgumentException;

/**
 * Something that happened in a session, typed and ready for a trigger to
 * select: a penalty, a flag, a result row. It carries who it concerns, its
 * instance and event keys, its evidence and named attributes. An attribute
 * can be a closure, computed the first time it is read.
 */
final class Fact
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  int|null  $carsNamed  Cars named in the message it rests on, for the fairness rule
     */
    public function __construct(
        public readonly string $type,
        public readonly ?int $entryId,
        public readonly string $instanceKey,
        public readonly string $eventKey,
        private array $attributes,
        public readonly FactEvidence $evidence,
        public readonly AwardConfidence $confidence = AwardConfidence::High,
        public readonly ?AwardReviewReason $reviewReason = null,
        public readonly bool $isOpen = false,
        public readonly ?int $carsNamed = null,
    ) {}

    /**
     * An attribute by name, or a value inside one with a dot ("surprise.detail").
     * "entry" is the driver entry ID.
     */
    public function get(string $path): mixed
    {
        [$name, $rest] = array_pad(explode('.', $path, 2), 2, null);

        if ($name === 'entry') {
            return $this->entryId;
        }

        if (! array_key_exists($name, $this->attributes)) {
            throw new InvalidArgumentException("A {$this->type} fact has no attribute [{$name}].");
        }

        if ($this->attributes[$name] instanceof Closure) {
            $this->attributes[$name] = ($this->attributes[$name])();
        }

        return $rest === null ? $this->attributes[$name] : data_get($this->attributes[$name], $rest);
    }
}
