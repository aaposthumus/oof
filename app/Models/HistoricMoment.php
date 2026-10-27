<?php

namespace App\Models;

use App\Enums\HistoricMomentStatus;
use Database\Factories\HistoricMomentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A curated moment, ready to post about. Kept apart from candidates, which
 * are raw excavation output; a moment may also be written without one. It stays a draft until published.
 */
#[Fillable(['moment_candidate_id', 'round_id', 'date', 'title', 'story', 'tags', 'era', 'status'])]
class HistoricMoment extends Model
{
    /** @use HasFactory<HistoricMomentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'tags' => 'array',
            'status' => HistoricMomentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<MomentCandidate, $this>
     */
    public function momentCandidate(): BelongsTo
    {
        return $this->belongsTo(MomentCandidate::class);
    }

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * The note to show a reviewer when this moment touches something
     * sensitive: the candidate's own note, else a sensitivity entry on the
     * round that covers the whole round or the candidate's driver.
     */
    public function sensitivityNote(): ?string
    {
        $candidate = $this->momentCandidate;

        if ($candidate?->is_sensitive) {
            return $candidate->sensitivity_note ?: 'Marked sensitive, but no note was recorded.';
        }

        return MomentSensitivity::query()
            ->where('round_id', $this->round_id)
            ->where(fn ($query) => $query->whereNull('driver_id')->orWhere('driver_id', $candidate?->driver_id))
            ->orderByRaw('driver_id is null')
            ->value('note');
    }
}
