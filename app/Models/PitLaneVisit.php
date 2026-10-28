<?php

namespace App\Models;

use Database\Factories\PitLaneVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pass through the pit lane from OpenF1, in any session. A race visit links
 * to the Jolpica pit stop it corresponds to, if any.
 */
#[Fillable(['race_session_id', 'driver_number', 'driver_entry_id', 'pit_stop_id', 'lap_number', 'entered_at', 'lane_duration_millis', 'stop_duration_millis'])]
class PitLaneVisit extends Model
{
    /** @use HasFactory<PitLaneVisitFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
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

    /**
     * @return BelongsTo<PitStop, $this>
     */
    public function pitStop(): BelongsTo
    {
        return $this->belongsTo(PitStop::class);
    }
}
