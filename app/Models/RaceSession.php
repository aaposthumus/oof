<?php

namespace App\Models;

use App\Enums\SessionType;
use Carbon\CarbonImmutable;
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
     * When the session ended: OpenF1's end time, or else its scheduled start
     * (the round's date and time when it has none) plus three hours.
     */
    public function endsAt(): CarbonImmutable
    {
        $this->loadMissing(['round', 'openF1Session']);

        if ($this->openF1Session?->date_end !== null) {
            return CarbonImmutable::parse($this->openF1Session->date_end);
        }

        $date = $this->scheduled_date ?? $this->round->date;
        $time = $this->scheduled_time ?? $this->round->time ?? '23:59:59';

        return CarbonImmutable::parse($date->format('Y-m-d').' '.$time, 'UTC')->addHours(3);
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
     * @return HasMany<SessionLap, $this>
     */
    public function sessionLaps(): HasMany
    {
        return $this->hasMany(SessionLap::class);
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
