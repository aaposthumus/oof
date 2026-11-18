<?php

namespace App\Models;

use App\Achievements\OofScoreStandings;
use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\AwardScope;
use App\Enums\RecipientType;
use App\Enums\ScoreCategory;
use App\Enums\SessionType;
use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * An achievement in the catalogue. The key ties it to its entry in
 * config/achievement_catalogue.php, which seeds the row: editorial fields
 * are edited in the CMS, mechanics are written by oof:achievements:sync.
 *
 * @property list<string> $session_types SessionType values it applies to
 * @property list<string> $supersedes Keys of achievements it replaces for the same event
 * @property list<string>|null $data_sources DataSource values, derived from the trigger
 * @property int|null $available_from The first year its data supports, derived from the trigger
 */
#[Fillable(['key', 'name', 'section', 'shelf', 'recipient_type', 'tier', 'session_types', 'scope', 'decided_after', 'stacks', 'supersedes', 'description', 'is_enabled', 'withheld_on_sensitive_weekends', 'is_manual', 'score_category', 'data_sources', 'available_from'])]
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
            'recipient_type' => RecipientType::class,
            'tier' => AchievementTier::class,
            'session_types' => 'array',
            'scope' => AwardScope::class,
            'stacks' => 'boolean',
            'supersedes' => 'array',
            'is_enabled' => 'boolean',
            'withheld_on_sensitive_weekends' => 'boolean',
            'is_manual' => 'boolean',
            'score_category' => ScoreCategory::class,
            'data_sources' => 'array',
            'available_from' => 'integer',
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
     * Who receives it: the catalogue's recipient, written by the sync
     * (a driver when the row does not say).
     */
    public function recipientType(): RecipientType
    {
        return $this->recipient_type ?? RecipientType::Driver;
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

    /**
     * Whether it is looked for in a session of this type: enabled, decided
     * per session and listing the type.
     */
    public function appliesTo(SessionType $type): bool
    {
        return $this->is_enabled && $this->scope === AwardScope::Session && in_array($type->value, $this->session_types, true);
    }

    /**
     * Whether it is decided over this scope and enabled.
     */
    public function isDecidedOver(AwardScope $scope): bool
    {
        return $this->is_enabled && $this->scope === $scope;
    }

    /**
     * The session of the round a weekend achievement is decided after: the
     * last practice session for "practice", else the session of that type.
     *
     * @param  Collection<int, RaceSession>  $sessions  The round's sessions
     */
    public function decidingSession(Collection $sessions): ?RaceSession
    {
        if ($this->decided_after === 'practice') {
            return $sessions->filter(fn (RaceSession $session) => $session->type->isPractice())->sortBy(fn (RaceSession $session) => $session->type->weekendOrder())->last();
        }

        return $sessions->first(fn (RaceSession $session) => $session->type->value === $this->decided_after);
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
