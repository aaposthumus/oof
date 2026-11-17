<?php

namespace App\Models;

use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use App\Enums\SensitivitySource;
use Database\Factories\MomentSensitivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a round, or one driver in it, as sensitive (candidates are kept but
 * flagged for review) or blocked (excavation skips it). Without a driver it
 * covers the whole round; either way it flags every candidate of its round
 * (only a block is limited to its driver). The severity (fatal, injury or
 * minor) decides whether it also flags stories that span the season: those
 * only take fatal entries and injuries to the drivers involved.
 *
 * The engine can propose one per round after a heavy crash (source engine),
 * covering the whole round and listing the drivers involved. It acts as
 * sensitive until a person confirms it; once dismissed it is ignored
 * everywhere, and the engine does not propose it again. Any change sends
 * the round's sessions back to the achievements engine. After a dismissal or
 * a deletion, the decisions people had made on awards the system suppressed
 * are given back wherever the remaining sensitivities allow them.
 */
#[Fillable(['round_id', 'driver_id', 'involved_driver_ids', 'level', 'severity', 'source', 'confirmed_at', 'dismissed_at', 'note'])]
class MomentSensitivity extends Model
{
    /** @use HasFactory<MomentSensitivityFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => SensitivityLevel::class,
            'severity' => SensitivitySeverity::class,
            'source' => SensitivitySource::class,
            'involved_driver_ids' => 'array',
            'confirmed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $recheckRound = fn (self $sensitivity) => RaceSession::query()
            ->whereIn('round_id', array_filter([$sensitivity->round_id, $sensitivity->getOriginal('round_id')]))
            ->update(['achievements_checked_at' => null]);

        static::saved(function (self $sensitivity) use ($recheckRound): void {
            $recheckRound($sensitivity);

            if ($sensitivity->wasChanged('dismissed_at') && $sensitivity->dismissed_at !== null) {
                $sensitivity->releaseRound();
            }
        });

        static::deleted(function (self $sensitivity) use ($recheckRound): void {
            $recheckRound($sensitivity);
            $sensitivity->releaseRound();
        });
    }

    /**
     * Give back the decisions people had made on awards the system
     * suppressed, as far as the round's remaining sensitivities allow.
     */
    private function releaseRound(): void
    {
        AchievementAward::releaseRound($this->round_id);
    }

    /**
     * Sensitivities that count: all but dismissed proposals.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('dismissed_at');
    }

    public function isProposal(): bool
    {
        return $this->source === SensitivitySource::Engine && $this->confirmed_at === null && $this->dismissed_at === null;
    }

    public function confirm(): void
    {
        $this->update(['confirmed_at' => now(), 'dismissed_at' => null]);
    }

    public function dismiss(): void
    {
        $this->update(['dismissed_at' => now(), 'confirmed_at' => null]);
    }

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
