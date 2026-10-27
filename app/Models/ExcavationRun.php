<?php

namespace App\Models;

use Database\Factories\ExcavationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of one excavator, with the parameters it dug with.
 */
#[Fillable(['excavator', 'excavator_version', 'parameters', 'started_at', 'finished_at', 'candidates_found'])]
class ExcavationRun extends Model
{
    /** @use HasFactory<ExcavationRunFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MomentCandidate, $this>
     */
    public function momentCandidates(): HasMany
    {
        return $this->hasMany(MomentCandidate::class);
    }
}
