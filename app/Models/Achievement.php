<?php

namespace App\Models;

use App\Achievements\OofScoreStandings;
use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\RecipientType;
use App\Enums\ScoreCategory;
use App\Enums\SessionType;
use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An achievement in the catalogue. The key ties it to its detector; the rest
 * is seeded from docs/achievements.md and can be edited in the CMS.
 *
 * @property list<string> $session_types SessionType values it applies to
 * @property list<string> $supersedes Keys of achievements it replaces for the same event
 */
#[Fillable(['key', 'name', 'section', 'shelf', 'tier', 'session_types', 'stacks', 'supersedes', 'description', 'is_enabled', 'withheld_on_sensitive_weekends', 'score_category'])]
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section' => AchievementSection::class,
            'shelf' => AchievementShelf::class,
            'tier' => AchievementTier::class,
            'session_types' => 'array',
            'stacks' => 'boolean',
            'supersedes' => 'array',
            'is_enabled' => 'boolean',
            'withheld_on_sensitive_weekends' => 'boolean',
            'score_category' => ScoreCategory::class,
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $achievement): void {
            if ($achievement->wasChanged(['tier', 'shelf'])) {
                $achievement->updateOpenSeasonAwards();
            }

            // What an award is worth in the OOFscore changed, so the open seasons it appears in follow.
            if ($achievement->wasChanged(['tier', 'shelf', 'score_category'])) {
                app(OofScoreStandings::class)->rebuildSeasons($achievement->awards()->distinct()->pluck('season_id'));
            }
        });
    }

    /**
     * Give the awards of every season that is not closed the current tier,
     * shelf and points, so within a season the achievement is worth the same
     * everywhere. Closed seasons keep their snapshot.
     */
    public function updateOpenSeasonAwards(): int
    {
        return $this->awards()
            ->whereHas('season', fn ($query) => $query->whereNull('oofscore_closed_at'))
            ->update(['tier' => $this->tier?->value, 'shelf' => $this->shelf->value, 'points' => $this->points(), 'updated_at' => now()]);
    }

    /**
     * OOFscore points for one award, from config: the tier's points on a
     * scoring shelf, unless the achievement has its own value.
     */
    public function points(): int
    {
        if ($this->tier === null || ! in_array($this->shelf->value, config('achievements.points.scoring_shelves'), true)) {
            return 0;
        }

        return config("achievements.points.overrides.{$this->key}") ?? config("achievements.points.tiers.{$this->tier->value}");
    }

    /**
     * Who receives it. Until the catalogue states it (Phase 8): the Safety Car
     * for its own shelf, a driver for everything else.
     */
    public function recipientType(): RecipientType
    {
        return $this->shelf === AchievementShelf::SafetyCar ? RecipientType::SafetyCar : RecipientType::Driver;
    }

    /**
     * The OOFscore multiplier for a team at this form rank before the round
     * (null: no race in the window). Driver and surprise achievements are ×1.
     */
    public function scoreMultiplier(?int $formRank): float
    {
        $multipliers = config("achievements.points.multipliers.{$this->score_category->value}");

        if ($multipliers === null) {
            return 1.0;
        }

        $bands = config('achievements.points.multipliers.bands');
        $band = match (true) {
            $formRank !== null && $formRank <= $bands['top'] => 'top',
            $formRank !== null && $formRank <= $bands['middle'] => 'middle',
            default => 'rest',
        };

        return (float) $multipliers[$band];
    }

    public function appliesTo(SessionType $type): bool
    {
        return $this->is_enabled && in_array($type->value, $this->session_types, true);
    }

    /**
     * @return HasMany<AchievementAward, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(AchievementAward::class);
    }

    /**
     * @return HasMany<AchievementVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(AchievementVariant::class);
    }
}
