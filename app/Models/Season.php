<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['year', 'wikipedia_url'])]
class Season extends Model
{
    /**
     * @return HasMany<Round, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    /**
     * @return HasMany<SeasonTeam, $this>
     */
    public function seasonTeams(): HasMany
    {
        return $this->hasMany(SeasonTeam::class);
    }

    /**
     * @return HasMany<SeasonDriver, $this>
     */
    public function seasonDrivers(): HasMany
    {
        return $this->hasMany(SeasonDriver::class);
    }
}
