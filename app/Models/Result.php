<?php

namespace App\Models;

use App\Enums\SessionType;
use Database\Factories\ResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A race or sprint classification row; the session type of its entry tells which.
 */
#[Fillable(['driver_entry_id', 'grid', 'position', 'position_text', 'classification_order', 'is_classified', 'status', 'points', 'laps_completed', 'time_text', 'time_millis', 'fastest_lap_number', 'fastest_lap_rank', 'fastest_lap_time_millis'])]
class Result extends Model
{
    /** @use HasFactory<ResultFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_classified' => 'boolean',
            'points' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<DriverEntry, $this>
     */
    public function driverEntry(): BelongsTo
    {
        return $this->belongsTo(DriverEntry::class);
    }

    /**
     * @param  Builder<Result>  $query
     */
    public function scopeRace(Builder $query): void
    {
        $this->scopeOfSessionType($query, SessionType::Race);
    }

    /**
     * @param  Builder<Result>  $query
     */
    public function scopeSprint(Builder $query): void
    {
        $this->scopeOfSessionType($query, SessionType::Sprint);
    }

    /**
     * @param  Builder<Result>  $query
     */
    public function scopeOfSessionType(Builder $query, SessionType $type): void
    {
        $query->whereHas('driverEntry.raceSession', fn (Builder $query) => $query->where('type', $type));
    }
}
