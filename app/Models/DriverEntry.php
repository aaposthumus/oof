<?php

namespace App\Models;

use Database\Factories\DriverEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A driver taking part in one session for one team with one race number.
 */
#[Fillable(['race_session_id', 'driver_id', 'season_team_id', 'car_number'])]
class DriverEntry extends Model
{
    /** @use HasFactory<DriverEntryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RaceSession, $this>
     */
    public function raceSession(): BelongsTo
    {
        return $this->belongsTo(RaceSession::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<SeasonTeam, $this>
     */
    public function seasonTeam(): BelongsTo
    {
        return $this->belongsTo(SeasonTeam::class);
    }

    /**
     * @return HasOne<Result, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }

    /**
     * @return HasOne<QualifyingResult, $this>
     */
    public function qualifyingResult(): HasOne
    {
        return $this->hasOne(QualifyingResult::class);
    }

    /**
     * @return HasMany<PitStop, $this>
     */
    public function pitStops(): HasMany
    {
        return $this->hasMany(PitStop::class);
    }

    /**
     * @return HasMany<LapTime, $this>
     */
    public function lapTimes(): HasMany
    {
        return $this->hasMany(LapTime::class);
    }

    /**
     * @return HasMany<SessionLap, $this>
     */
    public function sessionLaps(): HasMany
    {
        return $this->hasMany(SessionLap::class);
    }

    /**
     * @return HasMany<Stint, $this>
     */
    public function stints(): HasMany
    {
        return $this->hasMany(Stint::class);
    }

    /**
     * @return HasMany<PitLaneVisit, $this>
     */
    public function pitLaneVisits(): HasMany
    {
        return $this->hasMany(PitLaneVisit::class);
    }
}
