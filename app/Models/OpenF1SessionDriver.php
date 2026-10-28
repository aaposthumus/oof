<?php

namespace App\Models;

use App\Enums\OpenF1MatchStatus;
use Database\Factories\OpenF1SessionDriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A car in an OpenF1 session as OpenF1 lists it, and the driver, team and
 * entry it was matched to. OpenF1 rows refer to cars by driver_number; this is
 * how they find their driver entry.
 */
#[Table('openf1_session_drivers')]
#[Fillable(['race_session_id', 'driver_number', 'name_acronym', 'first_name', 'last_name', 'full_name', 'broadcast_name', 'team_name', 'team_colour', 'headshot_reference', 'driver_id', 'season_team_id', 'driver_entry_id', 'match_status', 'match_note'])]
class OpenF1SessionDriver extends Model
{
    /** @use HasFactory<OpenF1SessionDriverFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_status' => OpenF1MatchStatus::class,
        ];
    }

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
     * @return BelongsTo<DriverEntry, $this>
     */
    public function driverEntry(): BelongsTo
    {
        return $this->belongsTo(DriverEntry::class);
    }
}
