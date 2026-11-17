<?php

namespace App\Models;

use App\Achievements\Rules\Sensitivity;
use App\Achievements\WithheldOnSensitiveWeekend;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\AwardScope;
use App\Enums\AwardSource;
use App\Enums\AwardStatus;
use App\Enums\RecipientType;
use Carbon\CarbonInterface;
use Database\Factories\AchievementAwardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An achievement unlocked in a context (today always a session), with the
 * evidence it came from. Safety Car awards have no driver entry. Once a
 * person has decided on an award (decided_at), re-running the engine never
 * changes that decision.
 *
 * Saving fills what follows from the rest: the context (scope, key, season,
 * round, calendar date) from the session, the season team and team from
 * the driver entry, and finalized_at the first time it is final. Tier,
 * shelf and points are a snapshot taken when the award is created. A tier
 * change in the catalogue updates the snapshot in seasons that are not
 * closed (Achievement::updateOpenSeasonAwards()); in a closed season it is
 * frozen, and a new award there takes the season's existing snapshot.
 *
 * @property array{source: string, messages?: list<array<string, mixed>>, rows?: list<array<string, mixed>>, note?: string} $evidence
 */
#[Fillable(['season_id', 'round_id', 'race_session_id', 'scope', 'context_key', 'recipient_type', 'driver_entry_id', 'driver_id', 'season_team_id', 'team_id', 'achievement_id', 'tier', 'shelf', 'points', 'instance_key', 'event_key', 'detail', 'subtitle', 'variant_name', 'achievement_variant_id', 'occurred_on', 'occurred_md', 'source', 'is_sensitive', 'sensitivity_note', 'evidence', 'confidence', 'status', 'review_reason', 'decided_by', 'decided_at', 'decision_note', 'finalized_at', 'handed_back_at'])]
class AchievementAward extends Model
{
    /** @use HasFactory<AchievementAwardFactory> */
    use HasFactory;

    /**
     * The note on awards the engine suppressed itself in a historic session.
     */
    public const HISTORIC_NOTE = 'Historic, not reviewed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => AwardScope::class,
            'recipient_type' => RecipientType::class,
            'tier' => AchievementTier::class,
            'shelf' => AchievementShelf::class,
            'points' => 'integer',
            'occurred_on' => 'date',
            'occurred_md' => 'integer',
            'source' => AwardSource::class,
            'finalized_at' => 'datetime',
            'is_sensitive' => 'boolean',
            'evidence' => 'array',
            'confidence' => AwardConfidence::class,
            'status' => AwardStatus::class,
            'review_reason' => AwardReviewReason::class,
            'decided_at' => 'datetime',
            'handed_back_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $award): void {
            $award->fillDerived();
            $award->guardSensitivity();
        });
    }

    /**
     * month * 100 + day of a date, the form occurred_md is stored in.
     */
    public static function monthDay(CarbonInterface $date): int
    {
        return $date->month * 100 + $date->day;
    }

    /**
     * Awards of every year whose calendar date falls in the window, both
     * ends included. A window that runs past New Year (28 December to
     * 3 January) wraps.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInCalendarWindow(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $start = self::monthDay($from);
        $end = self::monthDay($to);

        $start <= $end
            ? $query->whereBetween('occurred_md', [$start, $end])
            : $query->where(fn (Builder $query) => $query->where('occurred_md', '>=', $start)->orWhere('occurred_md', '<=', $end));
    }

    /**
     * The sensitive flag never clears. Nothing may be awarded in a blocked
     * scope, nor a withheld achievement (Built Different among them) on a
     * sensitive weekend, unless it is stored suppressed.
     *
     * @throws WithheldOnSensitiveWeekend
     */
    private function guardSensitivity(): void
    {
        if ($this->getOriginal('is_sensitive') === true) {
            $this->is_sensitive = true;
        }

        if ($this->round_id === null || $this->status === AwardStatus::Suppressed || ($this->exists && ! $this->isDirty(['status', 'driver_id', 'achievement_id', 'round_id']))) {
            return;
        }

        $achievement = Achievement::query()->findOrFail($this->achievement_id);

        if (! Sensitivity::allows(Sensitivity::forRound($this->round_id), $achievement, $this->driver_id)) {
            throw new WithheldOnSensitiveWeekend("{$achievement->name} cannot be awarded here: the weekend is sensitive or blocked.");
        }
    }

    private function fillDerived(): void
    {
        if ($this->race_session_id !== null && ($this->context_key === null || $this->isDirty('race_session_id'))) {
            $session = RaceSession::query()->with('round')->findOrFail($this->race_session_id);
            $date = $session->scheduled_date ?? $session->round->date;

            $this->fill([
                'scope' => AwardScope::Session,
                'context_key' => "session:{$session->id}",
                'season_id' => $session->round->season_id,
                'round_id' => $session->round_id,
                'occurred_on' => $date,
                'occurred_md' => self::monthDay($date),
            ]);
        }

        if (! $this->exists && $this->shelf === null) {
            $achievement = Achievement::query()->findOrFail($this->achievement_id);
            $frozen = Season::query()->find($this->season_id)?->isClosed()
                ? self::query()->where('season_id', $this->season_id)->where('achievement_id', $this->achievement_id)->first(['tier', 'shelf', 'points'])
                : null;

            $this->fill([
                'tier' => $frozen === null ? $achievement->tier : $frozen->tier,
                'shelf' => $frozen === null ? $achievement->shelf : $frozen->shelf,
                'points' => $frozen === null ? $achievement->points() : $frozen->points,
                'recipient_type' => $this->recipient_type ?? $achievement->recipientType(),
            ]);
        }

        if (! $this->exists || $this->isDirty('driver_entry_id')) {
            $entry = $this->driver_entry_id === null ? null : DriverEntry::query()->with('seasonTeam')->find($this->driver_entry_id);
            $this->season_team_id = $entry?->season_team_id;
            $this->team_id = $entry?->seasonTeam?->team_id;
        }

        $this->source ??= AwardSource::Engine;

        if ($this->status === AwardStatus::Final && $this->finalized_at === null) {
            $this->finalized_at = now();
        }
    }

    /**
     * Whether a person decided on it. The engine's own suppression of a
     * historic award does not count: it judges that award again on each run.
     */
    public function isDecided(): bool
    {
        return $this->decided_at !== null && ! $this->isSuppressedAsHistoric();
    }

    public function isSuppressedAsHistoric(): bool
    {
        return $this->status === AwardStatus::Suppressed && $this->decided_by === null && $this->decision_note === self::HISTORIC_NOTE;
    }

    /**
     * The name as shown, with its detail: "Bumper Cars · 10 s".
     */
    public function title(): string
    {
        $name = $this->variant_name ?? $this->achievement->name;

        return $this->detail === null ? $name : "{$name} · {$this->detail}";
    }

    /**
     * The achievement's description from the catalogue, then the award's own subtitle.
     */
    public function descriptionLine(): ?string
    {
        return collect([$this->achievement->description, $this->subtitle])->filter()->implode(' · ') ?: null;
    }

    public function suppress(User $user, string $reason): void
    {
        $this->update(['status' => AwardStatus::Suppressed, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $reason]);
    }

    public function confirm(User $user, ?DriverEntry $entry = null): void
    {
        $this->update([
            'status' => AwardStatus::Final,
            'driver_entry_id' => $entry->id ?? $this->driver_entry_id,
            'driver_id' => $entry->driver_id ?? $this->driver_id,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'decision_note' => null,
        ]);
    }

    /**
     * Hand the award back to the engine, which re-evaluates it on its next run.
     * A historic award handed back stays in the review queue.
     */
    public function restore(): void
    {
        $this->update(['status' => AwardStatus::Pending, 'decided_by' => null, 'decided_at' => null, 'decision_note' => null, 'handed_back_at' => now()]);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * @return BelongsTo<SeasonTeam, $this>
     */
    public function seasonTeam(): BelongsTo
    {
        return $this->belongsTo(SeasonTeam::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<AchievementVariant, $this>
     */
    public function achievementVariant(): BelongsTo
    {
        return $this->belongsTo(AchievementVariant::class);
    }

    /**
     * @return BelongsTo<RaceSession, $this>
     */
    public function raceSession(): BelongsTo
    {
        return $this->belongsTo(RaceSession::class);
    }

    /**
     * @return BelongsTo<DriverEntry, $this>
     */
    public function driverEntry(): BelongsTo
    {
        return $this->belongsTo(DriverEntry::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<Achievement, $this>
     */
    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
