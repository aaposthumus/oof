<?php

namespace App\Models;

use App\Enums\OpenF1MatchStatus;
use Database\Factories\OpenF1SessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An OpenF1 session and the race session it was matched to or created as, if
 * any.
 */
#[Table('openf1_sessions')]
#[Fillable(['session_key', 'meeting_key', 'year', 'name', 'session_type', 'date_start', 'date_end', 'is_cancelled', 'race_session_id', 'match_status', 'match_note'])]
class OpenF1Session extends Model
{
    /** @use HasFactory<OpenF1SessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_start' => 'datetime',
            'date_end' => 'datetime',
            'is_cancelled' => 'boolean',
            'match_status' => OpenF1MatchStatus::class,
        ];
    }

    /**
     * @return BelongsTo<RaceSession, $this>
     */
    public function raceSession(): BelongsTo
    {
        return $this->belongsTo(RaceSession::class);
    }
}
