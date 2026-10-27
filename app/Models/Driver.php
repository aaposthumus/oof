<?php

namespace App\Models;

use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * permanent_number is for reference only; the number actually raced lives on driver_entries.
 */
#[Fillable(['jolpica_driver_id', 'given_name', 'family_name', 'code', 'permanent_number', 'date_of_birth', 'nationality', 'wikipedia_url'])]
class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    /**
     * @return HasMany<DriverEntry, $this>
     */
    public function driverEntries(): HasMany
    {
        return $this->hasMany(DriverEntry::class);
    }

    /**
     * @return HasMany<SeasonDriver, $this>
     */
    public function seasonDrivers(): HasMany
    {
        return $this->hasMany(SeasonDriver::class);
    }
}
