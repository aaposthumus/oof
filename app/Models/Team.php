<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The lasting identity of a team across renames (e.g. Sauber → Audi).
 */
#[Fillable(['slug', 'name'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * @return HasMany<SeasonTeam, $this>
     */
    public function seasonTeams(): HasMany
    {
        return $this->hasMany(SeasonTeam::class);
    }
}
