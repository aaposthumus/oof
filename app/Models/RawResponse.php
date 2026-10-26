<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source', 'endpoint', 'request_key', 'url', 'parameters', 'season', 'round', 'http_status', 'total_rows', 'payload', 'fetched_at', 'normalized_at'])]
class RawResponse extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'payload' => 'array',
            'fetched_at' => 'datetime',
            'normalized_at' => 'datetime',
        ];
    }

    /**
     * Responses that were never normalized, or were fetched again after normalizing.
     *
     * @param  Builder<RawResponse>  $query
     */
    public function scopePendingNormalization(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereNull('normalized_at')->orWhereColumn('normalized_at', '<', 'fetched_at');
        });
    }
}
