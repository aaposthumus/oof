<?php

namespace App\Models;

use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['year', 'wikipedia_url'])]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    /**
     * The ID of the season of the current year, or else the latest one.
     */
    public static function currentId(): ?int
    {
        return self::query()->where('year', now()->year)->value('id') ?? self::query()->orderByDesc('year')->value('id');
    }

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
