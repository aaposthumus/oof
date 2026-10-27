<?php

namespace App\Models;

use App\Enums\SessionType;
use Database\Factories\RaceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['round_id', 'type', 'scheduled_date', 'scheduled_time'])]
class RaceSession extends Model
{
    /** @use HasFactory<RaceSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SessionType::class,
            'scheduled_date' => 'date',
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
     * @return HasMany<DriverEntry, $this>
     */
    public function driverEntries(): HasMany
    {
        return $this->hasMany(DriverEntry::class);
    }
}
