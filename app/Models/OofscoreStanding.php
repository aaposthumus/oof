<?php

namespace App\Models;

use Database\Factories\OofscoreStandingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A driver's OOFscore after a round: the points of their final awards on
 * the scoring shelves so far that season, their place (equal points share
 * it) and how many places they moved since the round before (positive is
 * up, null when they were not in the standings yet).
 */
#[Fillable(['season_id', 'round_id', 'driver_id', 'points', 'awards_count', 'position', 'position_change'])]
class OofscoreStanding extends Model
{
    /** @use HasFactory<OofscoreStandingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'awards_count' => 'integer',
            'position' => 'integer',
            'position_change' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
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
}
