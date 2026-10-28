<?php

namespace App\Models;

use Database\Factories\TeamRadioMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A published team radio message. Only the listing is kept; the audio is never
 * downloaded.
 */
#[Fillable(['race_session_id', 'driver_number', 'driver_entry_id', 'recorded_at', 'recording_url'])]
class TeamRadioMessage extends Model
{
    /** @use HasFactory<TeamRadioMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RaceSession, $this>
     */
    public function raceSession(): BelongsTo
    {
        return $this->belongsTo(RaceSession::class);
    }

    /**
     * @return BelongsTo<DriverEntry, $this>
     */
    public function driverEntry(): BelongsTo
    {
        return $this->belongsTo(DriverEntry::class);
    }
}
