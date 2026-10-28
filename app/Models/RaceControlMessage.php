<?php

namespace App\Models;

use Database\Factories\RaceControlMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A race control message as OpenF1 has it. car_number is the car it is about:
 * OpenF1's driver_number, or else the only car named in the text.
 */
#[Fillable(['race_session_id', 'fingerprint', 'issued_at', 'lap_number', 'category', 'flag', 'scope', 'sector', 'qualifying_phase', 'driver_number', 'car_number', 'driver_entry_id', 'car_numbers', 'message'])]
class RaceControlMessage extends Model
{
    /** @use HasFactory<RaceControlMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'car_numbers' => 'array',
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
