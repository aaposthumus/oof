<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A round of a season. Its name, short name and country are its own and are
 * never derived from the circuit it was held at.
 */
#[Fillable(['season_id', 'round_number', 'circuit_id', 'name', 'short_name', 'country_name', 'country_code', 'jolpica_race_name', 'date', 'time', 'wikipedia_url'])]
class Round extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Circuit, $this>
     */
    public function circuit(): BelongsTo
    {
        return $this->belongsTo(Circuit::class);
    }

    /**
     * @return HasMany<RaceSession, $this>
     */
    public function raceSessions(): HasMany
    {
        return $this->hasMany(RaceSession::class);
    }

    /**
     * @return HasMany<DriverStanding, $this>
     */
    public function driverStandings(): HasMany
    {
        return $this->hasMany(DriverStanding::class);
    }

    /**
     * @return HasMany<ConstructorStanding, $this>
     */
    public function constructorStandings(): HasMany
    {
        return $this->hasMany(ConstructorStanding::class);
    }
}
