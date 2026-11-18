<?php

namespace App\Models;

use App\Achievements\Nicknames\OffSeasonOnly;
use App\Enums\Breakthrough;
use App\Enums\EvolutionStatus;
use App\Enums\NicknameStatus;
use App\Events\NicknameEvolved;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\NicknameEvolutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * A driver's nickname moving to its planned next stage. The engine proposes
 * it after a breakthrough in a season (Breakthroughs); a person approves it
 * with an effective date in the off-season after that season, or dismisses
 * it; on the effective date it is applied (oof:nicknames:evolve) and
 * NicknameEvolved is dispatched. One per target nickname.
 *
 * @property array{rows?: list<array<string, mixed>>, note?: string} $evidence
 */
#[Fillable(['driver_id', 'from_nickname_id', 'to_nickname_id', 'season_id', 'trigger_award_id', 'reason', 'evidence', 'status', 'decided_by', 'decided_at', 'effective_on', 'applied_at'])]
class NicknameEvolution extends Model
{
    /** @use HasFactory<NicknameEvolutionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => Breakthrough::class,
            'evidence' => 'array',
            'status' => EvolutionStatus::class,
            'decided_at' => 'datetime',
            'effective_on' => 'date',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * The dates it may take effect: after the season's last round and before
     * the next season's first round. Null when the next season's calendar is
     * not known yet.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}|null
     */
    public function offSeason(): ?array
    {
        $lastRound = Round::query()->where('season_id', $this->season_id)->max('date');
        $nextSeason = Season::query()->where('year', $this->season->year + 1)->first();
        $firstRound = $nextSeason === null ? null : Round::query()->where('season_id', $nextSeason->id)->min('date');

        if ($lastRound === null || $firstRound === null) {
            return null;
        }

        return ['from' => CarbonImmutable::parse($lastRound)->addDay(), 'to' => CarbonImmutable::parse($firstRound)->subDay()];
    }

    /**
     * @throws OffSeasonOnly When the date is not in the off-season after the breakthrough's season
     */
    public function approve(User $user, CarbonInterface $effectiveOn): void
    {
        if (! in_array($this->status, [EvolutionStatus::Proposed, EvolutionStatus::Approved], true)) {
            throw new LogicException("An evolution that is {$this->status->value} cannot be approved.");
        }

        $window = $this->offSeason();

        if ($window === null) {
            throw new OffSeasonOnly("The calendar of {$this->season->year} or ".($this->season->year + 1).' is not known yet, so the off-season has no dates.');
        }

        if ($effectiveOn->lt($window['from']) || $effectiveOn->gt($window['to'])) {
            throw new OffSeasonOnly("An evolution takes effect in the off-season: between {$window['from']->toDateString()} and {$window['to']->toDateString()}.");
        }

        $this->update(['status' => EvolutionStatus::Approved, 'decided_by' => $user->id, 'decided_at' => now(), 'effective_on' => $effectiveOn->toDateString()]);
    }

    public function dismiss(User $user): void
    {
        if ($this->status === EvolutionStatus::Applied) {
            throw new LogicException('An applied evolution cannot be dismissed.');
        }

        $this->update(['status' => EvolutionStatus::Dismissed, 'decided_by' => $user->id, 'decided_at' => now(), 'effective_on' => null]);
    }

    /**
     * Make the next stage the driver's nickname from the effective date, the
     * old one ending the day before. NicknameEvolved follows the commit, for
     * seasons from achievements.events_from.
     */
    public function apply(): void
    {
        if ($this->status !== EvolutionStatus::Approved || $this->effective_on === null) {
            throw new LogicException('Only an approved evolution with an effective date can be applied.');
        }

        DB::transaction(function (): void {
            $this->from->update(['status' => NicknameStatus::Retired, 'active_to' => $this->effective_on->copy()->subDay()]);
            $this->to->update(['status' => NicknameStatus::Active, 'active_from' => $this->effective_on]);
            $this->update(['status' => EvolutionStatus::Applied, 'applied_at' => now()]);

            if ($this->season->year >= (int) config('achievements.events_from')) {
                NicknameEvolved::dispatch($this);
            }
        });
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<DriverNickname, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(DriverNickname::class, 'from_nickname_id');
    }

    /**
     * @return BelongsTo<DriverNickname, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(DriverNickname::class, 'to_nickname_id');
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * The award behind the breakthrough; none for a strong first season.
     *
     * @return BelongsTo<AchievementAward, $this>
     */
    public function triggerAward(): BelongsTo
    {
        return $this->belongsTo(AchievementAward::class, 'trigger_award_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
