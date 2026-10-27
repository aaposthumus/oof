<?php

namespace App\Models;

use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use Database\Factories\MomentSensitivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a round, or one driver in it, as sensitive (candidates are kept but
 * flagged for review) or blocked (excavation skips it). Without a driver it
 * covers the whole round. The severity (fatal or injury) decides whether it
 * also flags stories that span the season: those only take fatal entries and
 * injuries to the drivers involved.
 */
#[Fillable(['round_id', 'driver_id', 'level', 'severity', 'note'])]
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
        ];
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
