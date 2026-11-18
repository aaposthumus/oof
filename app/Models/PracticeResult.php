<?php

namespace App\Models;

use Database\Factories\PracticeResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A car's place in a practice session (OpenF1 session results): its
 * position, best lap and the laps it drove.
 */
#[Fillable(['driver_entry_id', 'position', 'best_lap_millis', 'laps'])]
class PracticeResult extends Model
{
    /** @use HasFactory<PracticeResultFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<DriverEntry, $this>
     */
    public function driverEntry(): BelongsTo
    {
        return $this->belongsTo(DriverEntry::class);
    }
}
