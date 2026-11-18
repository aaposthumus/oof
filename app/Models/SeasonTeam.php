<?php

namespace App\Models;

use App\Enums\RecipientType;
use Database\Factories\SeasonTeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['season_id', 'team_id', 'jolpica_constructor_id', 'name', 'nationality', 'primary_colour', 'secondary_colour'])]
class SeasonTeam extends Model
{
    /** @use HasFactory<SeasonTeamFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<DriverEntry, $this>
     */
    public function driverEntries(): HasMany
    {
        return $this->hasMany(DriverEntry::class);
    }

    /**
     * The team's own awards in this season (see AchievementAward::forSeasonTeam()).
     *
     * @return HasMany<AchievementAward, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(AchievementAward::class)->where('recipient_type', RecipientType::Team);
    }
}
