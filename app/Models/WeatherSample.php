<?php

namespace App\Models;

use Database\Factories\WeatherSampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A weather reading from OpenF1, about one a minute.
 */
#[Fillable(['race_session_id', 'recorded_at', 'air_temperature', 'track_temperature', 'humidity', 'pressure', 'rainfall', 'wind_speed', 'wind_direction'])]
class WeatherSample extends Model
{
    /** @use HasFactory<WeatherSampleFactory> */
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
            'rainfall' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RaceSession, $this>
     */
    public function raceSession(): BelongsTo
    {
        return $this->belongsTo(RaceSession::class);
    }
}
