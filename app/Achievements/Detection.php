<?php

namespace App\Achievements;

use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;

/**
 * An achievement a detector found in a session, before the rules run.
 *
 * The instance key tells instances of the same achievement apart within the
 * session (e.g. one per penalty message). The event key ties together
 * detections from the same event, so a more specific achievement can replace
 * a less specific one: an incident thread, a car's result, a pit stop.
 */
final readonly class Detection
{
    /**
     * @param  array{source: string, messages?: list<array<string, mixed>>, rows?: list<array<string, mixed>>, note?: string}  $evidence
     * @param  int|null  $carsNamed  Cars named in the message a fault-based detection rests on
     * @param  bool  $isOpen  Whether it hangs on an investigation without an outcome yet
     */
    public function __construct(
        public string $achievementKey,
        public ?int $driverEntryId,
        public string $instanceKey,
        public string $eventKey,
        public array $evidence,
        public AwardConfidence $confidence = AwardConfidence::High,
        public ?string $detail = null,
        public ?AwardReviewReason $reviewReason = null,
        public bool $isOpen = false,
        public ?int $carsNamed = null,
    ) {}

    /**
     * The award's instance key: the entry first, so awards of different cars never collide.
     */
    public function awardInstanceKey(): string
    {
        return $this->driverEntryId === null ? $this->instanceKey : "e{$this->driverEntryId}:{$this->instanceKey}";
    }

    public function key(): string
    {
        return "{$this->achievementKey}|{$this->awardInstanceKey()}";
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
