<?php

namespace App\Excavation;

/**
 * One hit of an excavator. Round-level hits have no driver.
 */
final readonly class Candidate
{
    /**
     * @param  float  $score  0-100, comparable across excavators
     * @param  array<string, int|float|bool|string|null>  $evidence  The raw numbers behind the hit
     */
    public function __construct(
        public int $roundId,
        public ?int $driverId,
        public ?int $seasonTeamId,
        public float $score,
        public array $evidence,
        public bool $isSensitive = false,
        public ?string $sensitivityNote = null,
    ) {}

    /**
     * Turn a share of something going wrong (0-1) into a score of 0-100.
     */
    public static function scoreFromShare(float $share): float
    {
        return round(max(0, min(1, $share)) * 100, 2);
    }

    public function markSensitive(string $note): self
    {
        return new self($this->roundId, $this->driverId, $this->seasonTeamId, $this->score, $this->evidence, true, $note);
    }
}
