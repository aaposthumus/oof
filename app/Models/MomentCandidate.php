<?php

namespace App\Models;

use App\Enums\MomentCandidateStatus;
use Database\Factories\MomentCandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A possible historic moment found by an excavator, waiting for review. Its
 * type is the key of the excavator that found it. Its score is the percentile
 * rank (0-100, best = 100) of its raw score among all candidates of its type,
 * so candidates of different types can be compared; the raw score is kept in
 * the evidence as raw_score.
 */
#[Fillable(['excavation_run_id', 'type', 'round_id', 'driver_id', 'season_team_id', 'score', 'evidence', 'status', 'is_sensitive', 'sensitivity_note'])]
class MomentCandidate extends Model
{
    /** @use HasFactory<MomentCandidateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'evidence' => 'array',
            'status' => MomentCandidateStatus::class,
            'is_sensitive' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ExcavationRun, $this>
     */
    public function excavationRun(): BelongsTo
    {
        return $this->belongsTo(ExcavationRun::class);
    }

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<SeasonTeam, $this>
     */
    public function seasonTeam(): BelongsTo
    {
        return $this->belongsTo(SeasonTeam::class);
    }
}
