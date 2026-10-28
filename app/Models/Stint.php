<?php

namespace App\Models;

use Database\Factories\StintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tyre stint from OpenF1.
 */
#[Fillable(['race_session_id', 'driver_number', 'driver_entry_id', 'stint_number', 'compound', 'lap_start', 'lap_end', 'tyre_age_at_start'])]
class Stint extends Model
{
    /** @use HasFactory<StintFactory> */
    use HasFactory;

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
