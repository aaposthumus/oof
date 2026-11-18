<?php

namespace App\Models;

use App\Enums\AwardScope;
use Database\Factories\AchievementRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One context the achievements engine decided: a session ("session:12"), a
 * weekend ("round:7") or a season ("season:3"), with the catalogue version
 * it ran on (Catalogue::version()), the command's options and the stats per
 * achievement. Dry runs are logged too, marked as such, and never count for
 * --stale.
 *
 * @property array<string, mixed>|null $options
 * @property array<string, array<string, int>>|null $stats
 */
#[Fillable(['scope', 'context_key', 'season_id', 'catalogue_version', 'options', 'stats', 'is_dry_run', 'started_at', 'finished_at'])]
class AchievementRun extends Model
{
    /** @use HasFactory<AchievementRunFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => AwardScope::class,
            'options' => 'array',
            'stats' => 'array',
            'is_dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
