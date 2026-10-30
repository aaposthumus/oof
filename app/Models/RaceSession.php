<?php

namespace App\Models;

use App\Enums\SessionType;
use Database\Factories\RaceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['round_id', 'type', 'openf1_session_key', 'scheduled_date', 'scheduled_time', 'achievements_checked_at'])]
class RaceSession extends Model
{
    /** @use HasFactory<RaceSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SessionType::class,
            'scheduled_date' => 'date',
            'achievements_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * @return HasMany<DriverEntry, $this>
     */
    public function driverEntries(): HasMany
    {
        return $this->hasMany(DriverEntry::class);
    }

    /**
     * @return HasMany<OpenF1SessionDriver, $this>
     */
    public function openF1SessionDrivers(): HasMany
    {
        return $this->hasMany(OpenF1SessionDriver::class);
    }

    /**
     * @return HasMany<RaceControlMessage, $this>
     */
    public function raceControlMessages(): HasMany
    {
        return $this->hasMany(RaceControlMessage::class);
    }

    /**
     * @return HasMany<WeatherSample, $this>
     */
    public function weatherSamples(): HasMany
    {
        return $this->hasMany(WeatherSample::class);
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * @return HasMany<AchievementAward, $this>
     */
    public function achievementAwards(): HasMany
    {
        return $this->hasMany(AchievementAward::class);
    }

    /**
     * @return HasOne<OpenF1Session, $this>
     */
    public function openF1Session(): HasOne
    {
        return $this->hasOne(OpenF1Session::class);
    }
}
