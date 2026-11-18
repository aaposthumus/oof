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
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AchievementAwardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * An achievement unlocked in a context, with the evidence it came from: a
 * session ("session:12"), a weekend ("round:7", anchored to the session it
 * was decided after) or a season ("season:3", without round or session). Once a
 * person has decided on an award (decided_at), re-running the engine never
 * changes that decision.
 *
 * Its recipient (recipient_type) is a driver (driver_entry_id and driver_id;
 * the car may be unknown), a team (season_team_id and the lasting team_id,
 * no driver) or the Safety Car (none of them). A driver award also records
 * the team of its car. Query them with forDriver(), forTeam(),
 * forSeasonTeam() and forSafetyCar().
 *
 * Saving fills what follows from the rest: the context (key, season, round,
 * calendar date) from the session, or for a season from its final round; the
 * season team and team from the driver entry (for a team award, the team
 * from its season team); and finalized_at the first time it is final. Tier,
 * shelf and points are a snapshot taken when the award is created. A tier
 * change in the catalogue updates the snapshot in seasons that are not
 * closed (Achievement::updateOpenSeasonAwards()); in a closed season it is
 * frozen, and a new award there takes the season's existing snapshot.
 *
 * @property array{source: string, messages?: list<array<string, mixed>>, rows?: list<array<string, mixed>>, note?: string} $evidence
 */
#[Fillable(['season_id', 'round_id', 'race_session_id', 'scope', 'context_key', 'recipient_type', 'driver_entry_id', 'driver_id', 'season_team_id', 'team_id', 'achievement_id', 'tier', 'shelf', 'points', 'team_form_rank', 'score_multiplier', 'instance_key', 'event_key', 'detail', 'subtitle', 'variant_name', 'achievement_variant_id', 'occurred_on', 'occurred_md', 'source', 'is_sensitive', 'sensitivity_note', 'evidence', 'confidence', 'status', 'review_reason', 'decided_by', 'decided_at', 'decision_note', 'held_decision', 'finalized_at', 'handed_back_at'])]
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
            'team_form_rank' => 'integer',
            'score_multiplier' => 'float',
            'occurred_on' => 'date',
            'occurred_md' => 'integer',
            'source' => AwardSource::class,
            'finalized_at' => 'datetime',
            'is_sensitive' => 'boolean',
            'held_decision' => 'array',
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
     * Awards that may be published: final and not flagged sensitive. Any
     * publishing (automatic posts, comedic formats, feeds) must go through
     * this scope; suppressed, pending and flagged awards never leave the CMS.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublishable(Builder $query): void
    {
        $query->where('status', AwardStatus::Final)->where('is_sensitive', false);
    }

    /**
     * A driver's own awards, in every season (not those of their team).
     *
     * @param  Builder<self>  $query
     */
    public function scopeForDriver(Builder $query, Driver|int $driver): void
    {
        $query->where('recipient_type', RecipientType::Driver)->where('driver_id', $driver instanceof Driver ? $driver->id : $driver);
    }

    /**
     * A lasting team's own awards, in every season and under every name it
     * raced under (not those of its drivers).
     *
     * @param  Builder<self>  $query
     */
    public function scopeForTeam(Builder $query, Team|int $team): void
    {
        $query->where('recipient_type', RecipientType::Team)->where('team_id', $team instanceof Team ? $team->id : $team);
    }

    /**
     * A team's own awards in one season.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForSeasonTeam(Builder $query, SeasonTeam|int $seasonTeam): void
    {
        $query->where('recipient_type', RecipientType::Team)->where('season_team_id', $seasonTeam instanceof SeasonTeam ? $seasonTeam->id : $seasonTeam);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForSafetyCar(Builder $query): void
    {
        $query->where('recipient_type', RecipientType::SafetyCar);
    }

    /**
     * Who received it, as shown: the driver's name, the team's name, "Safety
     * Car", or null for a driver award whose car is not known yet.
     */
    public function recipientLabel(): ?string
    {
        return match ($this->recipient_type) {
            RecipientType::Team => $this->seasonTeam?->name,
            RecipientType::SafetyCar => 'Safety Car',
            default => $this->driver === null ? null : "{$this->driver->given_name} {$this->driver->family_name}",
        };
    }

    /**
     * Suppress it because a sensitive or blocked weekend does not allow it,
     * keeping the decision a person had made so it can come back.
     */
    public function suppressBySystem(?string $sensitivityNote): void
    {
        $this->update([
            'held_decision' => $this->decided_by === null ? null : [
                'status' => $this->status->value,
                'decided_by' => $this->decided_by,
                'decided_at' => $this->decided_at?->toIso8601String(),
                'decision_note' => $this->decision_note,
            ],
            'status' => AwardStatus::Suppressed,
            'decided_by' => null,
            'decided_at' => now(),
            'decision_note' => Sensitivity::SYSTEM_NOTE,
            'is_sensitive' => true,
            'sensitivity_note' => $sensitivityNote,
        ]);
    }

    /**
     * Give back the decisions people had made on awards of a round that the
     * system suppressed, wherever the round's remaining sensitivities allow
     * the award (it stays flagged). Ones the engine made itself and that are
     * now allowed are removed: the next run judges them again.
     *
     * @return int The awards given back or removed
     */
    public static function releaseRound(int $roundId): int
    {
        $remaining = Sensitivity::forRound($roundId);

        return self::query()
            ->with('achievement')
            ->where('round_id', $roundId)
            ->where('decision_note', Sensitivity::SYSTEM_NOTE)
            ->get()
            ->filter(fn (self $award) => Sensitivity::allows($remaining, $award->achievement, $award->driver_id))
            ->each(function (self $award): void {
                $held = $award->held_decision;

                if ($held === null) {
                    $award->delete();

                    return;
                }

                $award->update([
                    'status' => AwardStatus::from($held['status']),
                    'decided_by' => $held['decided_by'],
                    'decided_at' => $held['decided_at'],
                    'decision_note' => $held['decision_note'],
                    'held_decision' => null,
                ]);
            })
            ->count();
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
        $this->scope ??= AwardScope::Session;

        // A session or weekend award takes its context from its session (a weekend's deciding session).
        if ($this->scope !== AwardScope::Season && $this->race_session_id !== null && ($this->context_key === null || $this->isDirty('race_session_id'))) {
            $session = RaceSession::query()->with('round')->findOrFail($this->race_session_id);
            $date = $session->scheduled_date ?? $session->round->date;

            $this->fill([
                'context_key' => $this->scope === AwardScope::Weekend ? "round:{$session->round_id}" : "session:{$session->id}",
                'season_id' => $session->round->season_id,
                'round_id' => $session->round_id,
                'occurred_on' => $date,
                'occurred_md' => self::monthDay($date),
            ]);
        }

        // A season award has only its season, dated on the final round.
        if ($this->scope === AwardScope::Season && (! $this->exists || $this->isDirty('season_id'))) {
            $date = Round::query()->where('season_id', $this->season_id)->orderByDesc('round_number')->value('date');

            $this->fill([
                'context_key' => "season:{$this->season_id}",
                'round_id' => null,
                'race_session_id' => null,
                'occurred_on' => $date,
                'occurred_md' => $date === null ? null : self::monthDay(CarbonImmutable::parse($date)),
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

        // A team award names its season team itself; any other award takes its team from the car.
        if ($this->recipient_type === RecipientType::Team) {
            if (! $this->exists || $this->isDirty('season_team_id')) {
                $this->team_id = $this->season_team_id === null ? null : SeasonTeam::query()->find($this->season_team_id)?->team_id;
            }
        } elseif (! $this->exists || $this->isDirty('driver_entry_id')) {
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
     * The driver's nickname on the award's date. Never stored: it is read
     * from the nickname record, so a correction shows at once and an award
     * keeps the stage it was won under after an evolution.
     */
    public function nickname(): ?DriverNickname
    {
        return $this->recipient_type === RecipientType::Driver ? $this->driver?->nicknameOn($this->occurred_on) : null;
    }

    /**
     * The edition subtitle of a driver-specific variant, "Outside the Lines ·
     * MaxSettings edition", built from the achievement and the nickname
     * record; null without a variant or a nickname.
     */
    public function editionSubtitle(): ?string
    {
        $nickname = $this->achievement_variant_id === null ? null : $this->nickname();

        return $nickname === null ? null : "{$this->achievement->name} · {$nickname->nickname} edition";
    }

    /**
     * The achievement's description from the catalogue, then the award's own subtitle.
     */
    public function descriptionLine(): ?string
    {
        return collect([$this->achievement->description, $this->subtitle])->filter()->implode(' · ') ?: null;
    }

    /**
     * Award a manual achievement (Built Different) by hand: to the driver of
     * this car in its session, final at once and decided by the person, so
     * the engine keeps it. Refused for an achievement the engine detects, for
     * a session it does not apply to, twice for one car, and, through the
     * sensitivity guard, on a sensitive or blocked weekend.
     *
     * @throws InvalidArgumentException
     * @throws WithheldOnSensitiveWeekend
     */
    public static function awardByHand(Achievement $achievement, DriverEntry $entry, User $user, string $note): self
    {
        $entry->loadMissing('raceSession');

        if (! $achievement->is_manual || ! in_array($entry->raceSession->type->value, $achievement->session_types, true)) {
            throw new InvalidArgumentException("{$achievement->name} is not awarded by hand in this session.");
        }

        $instanceKey = "e{$entry->id}:manual";

        if (self::query()->where('achievement_id', $achievement->id)->where('race_session_id', $entry->race_session_id)->where('instance_key', $instanceKey)->exists()) {
            throw new InvalidArgumentException("{$achievement->name} was already awarded to this car in this session.");
        }

        return self::create([
            'scope' => AwardScope::Session,
            'race_session_id' => $entry->race_session_id,
            'recipient_type' => RecipientType::Driver,
            'driver_entry_id' => $entry->id,
            'driver_id' => $entry->driver_id,
            'achievement_id' => $achievement->id,
            'instance_key' => $instanceKey,
            'event_key' => "manual:{$entry->id}",
            'source' => AwardSource::Manual,
            'evidence' => ['source' => 'manual', 'note' => $note],
            'confidence' => AwardConfidence::High,
            'status' => AwardStatus::Final,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
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
