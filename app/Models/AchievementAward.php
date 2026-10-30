<?php

namespace App\Models;

use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use Database\Factories\AchievementAwardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An achievement unlocked in a session, with the evidence it came from.
 * Safety Car awards have no driver entry. Once a person has decided on an
 * award (decided_at), re-running the engine never changes that decision.
 *
 * @property array{source: string, messages?: list<array<string, mixed>>, rows?: list<array<string, mixed>>, note?: string} $evidence
 */
#[Fillable(['race_session_id', 'driver_entry_id', 'driver_id', 'achievement_id', 'instance_key', 'event_key', 'detail', 'subtitle', 'variant_name', 'evidence', 'confidence', 'status', 'review_reason', 'decided_by', 'decided_at', 'decision_note', 'handed_back_at'])]
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
            'evidence' => 'array',
            'confidence' => AwardConfidence::class,
            'status' => AwardStatus::class,
            'review_reason' => AwardReviewReason::class,
            'decided_at' => 'datetime',
            'handed_back_at' => 'datetime',
        ];
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
