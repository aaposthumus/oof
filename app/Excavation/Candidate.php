<?php

namespace App\Excavation;

/**
 * One hit of an excavator. Round-level hits have no driver.
 */
final readonly class Candidate
{
    /**
     * @param  float  $score  Raw score of the excavator, higher is more remarkable (mostly 0-100); the runner ranks it within the type
     * @param  array<string, int|float|bool|string|null>  $evidence  The raw numbers behind the hit
     * @param  list<int>  $relatedRoundIds  Other rounds the story spans; their sensitivities flag it, but never block it
     * @param  list<int>  $relatedDriverIds  The drivers the story is about; an injury to one of them in a related round flags it
     */
    public function __construct(
        public int $roundId,
        public ?int $driverId,
        public ?int $seasonTeamId,
        public float $score,
        public array $evidence,
        public bool $isSensitive = false,
        public ?string $sensitivityNote = null,
        public array $relatedRoundIds = [],
        public array $relatedDriverIds = [],
    ) {}

    /**
     * Turn a share of something going wrong (0-1) into a score of 0-100.
     */
    public static function scoreFromShare(float $share): float
    {
        return round(max(0, min(1, $share)) * 100, 2);
    }

    /**
     * The same hit with its percentile rank as score and the raw score kept in the evidence.
     */
    public function withRankedScore(float $score): self
    {
        return new self($this->roundId, $this->driverId, $this->seasonTeamId, $score, [...$this->evidence, 'raw_score' => $this->score], $this->isSensitive, $this->sensitivityNote, $this->relatedRoundIds, $this->relatedDriverIds);
    }

    public function markSensitive(string $note): self
    {
        return new self($this->roundId, $this->driverId, $this->seasonTeamId, $this->score, $this->evidence, true, $note, $this->relatedRoundIds, $this->relatedDriverIds);
    }
}
