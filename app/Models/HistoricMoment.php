<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A curated moment, ready to post about. Kept apart from candidates, which
 * are raw excavation output; a moment may also be written without one.
 */
#[Fillable(['moment_candidate_id', 'round_id', 'date', 'title', 'story', 'tags', 'era'])]
class HistoricMoment extends Model
{
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
}
