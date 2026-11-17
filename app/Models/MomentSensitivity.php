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
 * The engine can propose one after a heavy crash (source engine). It acts
 * as sensitive until a person confirms it; once dismissed it is ignored
 * everywhere, and the engine does not propose it again. Any change sends
 * the round's sessions back to the achievements engine.
 */
#[Fillable(['round_id', 'driver_id', 'level', 'severity', 'source', 'confirmed_at', 'dismissed_at', 'note'])]
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
            'confirmed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $recheckRound = fn (self $sensitivity) => RaceSession::query()
            ->whereIn('round_id', array_filter([$sensitivity->round_id, $sensitivity->getOriginal('round_id')]))
            ->update(['achievements_checked_at' => null]);

        static::saved($recheckRound);
        static::deleted($recheckRound);
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
