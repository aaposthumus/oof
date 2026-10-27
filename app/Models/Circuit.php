<?php

namespace App\Models;

use Database\Factories\CircuitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['jolpica_circuit_id', 'name', 'locality', 'country', 'latitude', 'longitude', 'wikipedia_url'])]
class Circuit extends Model
{
    /** @use HasFactory<CircuitFactory> */
    use HasFactory;

    /**
     * @return HasMany<Round, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }
}
