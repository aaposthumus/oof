<?php

namespace App\Models;

use Database\Factories\TeamLineageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rule mapping a Jolpica constructor to the lasting team it belongs to,
 * optionally limited to a range of seasons because Jolpica reuses some IDs
 * (alfa was both the 1950s works team and Sauber in 2019-2023). Rules for
 * one constructor never overlap; an unmapped constructor is its own team.
 */
#[Fillable(['jolpica_constructor_id', 'from_year', 'to_year', 'team_slug', 'note'])]
class TeamLineage extends Model
{
    /** @use HasFactory<TeamLineageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_year' => 'integer',
            'to_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_slug', 'slug');
    }

    /**
     * Every rule in the shape TeamResolver reads.
     *
     * @return list<array{constructor: string, team: string, from?: int, to?: int}>
     */
    public static function rules(): array
    {
        return self::query()
            ->orderBy('id')
            ->get()
            ->map(fn (self $lineage) => array_filter([
                'constructor' => $lineage->jolpica_constructor_id,
                'team' => $lineage->team_slug,
                'from' => $lineage->from_year,
                'to' => $lineage->to_year,
            ], fn ($value) => $value !== null))
            ->values()
            ->all();
    }

    /**
     * Whether another rule for the same constructor covers any of these seasons.
     */
    public static function overlaps(string $constructorId, ?int $fromYear, ?int $toYear, ?int $ignoreId = null): bool
    {
        return self::query()
            ->where('jolpica_constructor_id', $constructorId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->when($toYear !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('from_year')->orWhere('from_year', '<=', $toYear)))
            ->when($fromYear !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('to_year')->orWhere('to_year', '>=', $fromYear)))
            ->exists();
    }
}
