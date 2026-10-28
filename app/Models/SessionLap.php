<?php

namespace App\Models;

use Database\Factories\SessionLapFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lap from OpenF1, in any session. Kept apart from the Jolpica race
 * lap_times, which are the history from 1996 on.
 */
#[Fillable(['race_session_id', 'driver_number', 'driver_entry_id', 'lap_number', 'started_at', 'duration_millis', 'sector_1_millis', 'sector_2_millis', 'sector_3_millis', 'i1_speed', 'i2_speed', 'st_speed', 'is_pit_out_lap'])]
class SessionLap extends Model
{
    /** @use HasFactory<SessionLapFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'is_pit_out_lap' => 'boolean',
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
