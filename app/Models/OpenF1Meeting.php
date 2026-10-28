<?php

namespace App\Models;

use App\Enums\OpenF1MatchStatus;
use Database\Factories\OpenF1MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An OpenF1 meeting and the round it was matched to, if any. Every meeting is
 * kept, so nothing that fails to match goes unnoticed.
 */
#[Table('openf1_meetings')]
#[Fillable(['meeting_key', 'year', 'name', 'official_name', 'circuit_key', 'circuit_short_name', 'location', 'country_name', 'date_start', 'date_end', 'is_cancelled', 'round_id', 'match_status', 'match_note'])]
class OpenF1Meeting extends Model
{
    /** @use HasFactory<OpenF1MeetingFactory> */
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
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
