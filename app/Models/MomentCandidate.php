<?php

namespace App\Models;

use App\Enums\HistoricMomentStatus;
use App\Enums\MomentCandidateStatus;
use App\Excavation\Support\EvidenceSummary;
use Database\Factories\MomentCandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /**
     * @return HasOne<HistoricMoment, $this>
     */
    public function historicMoment(): HasOne
    {
        return $this->hasOne(HistoricMoment::class);
    }

    /**
     * Mark the candidate accepted and make sure a draft historic moment exists
     * for it, with a prefilled title and an empty story. Safe to repeat.
     */
    public function accept(): HistoricMoment
    {
        $this->loadMissing('round.season', 'driver');

        $this->update(['status' => MomentCandidateStatus::Accepted]);

        $year = $this->round->season->year;

        return $this->historicMoment()->firstOrCreate([], [
            'round_id' => $this->round_id,
            'date' => $this->round->date,
            'title' => "{$year} {$this->round->name}: ".EvidenceSummary::titlePhrase($this),
            'story' => '',
            'tags' => [],
            'era' => (intdiv($year, 10) * 10).'s',
            'status' => HistoricMomentStatus::Draft,
        ]);
    }

    /**
     * Set a status other than accepted; use accept() for that one. Leaving
     * accepted deletes the draft historic moment when nobody has worked on it
     * (see HistoricMoment::isUntouchedDraft()); an edited or published moment
     * is kept.
     */
    public function changeStatus(MomentCandidateStatus $status): void
    {
        if ($this->status === MomentCandidateStatus::Accepted && $status !== MomentCandidateStatus::Accepted) {
            $moment = $this->historicMoment;

            if ($moment?->isUntouchedDraft()) {
                $moment->delete();
                $this->unsetRelation('historicMoment');
            }
        }

        $this->update(['status' => $status]);
    }

    public function resetToNew(): void
    {
        $this->changeStatus(MomentCandidateStatus::New);
    }

    /**
     * Whether changing this accepted candidate to another status would leave
     * its historic moment in place, because it was edited or published.
     */
    public function keepsMomentWhenLeavingAccepted(): bool
    {
        return $this->status === MomentCandidateStatus::Accepted
            && $this->historicMoment !== null
            && ! $this->historicMoment->isUntouchedDraft();
    }
}
