<?php

namespace App\Models;

use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\SessionType;
use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An achievement in the catalogue. The key ties it to its detector; the rest
 * is seeded from docs/achievements.md and can be edited in the CMS.
 *
 * @property list<string> $session_types SessionType values it applies to
 * @property list<string> $supersedes Keys of achievements it replaces for the same event
 */
#[Fillable(['key', 'name', 'section', 'shelf', 'tier', 'points', 'session_types', 'stacks', 'supersedes', 'description', 'is_enabled'])]
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section' => AchievementSection::class,
            'shelf' => AchievementShelf::class,
            'tier' => AchievementTier::class,
            'session_types' => 'array',
            'stacks' => 'boolean',
            'supersedes' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function appliesTo(SessionType $type): bool
    {
        return $this->is_enabled && in_array($type->value, $this->session_types, true);
    }

    /**
     * @return HasMany<AchievementAward, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(AchievementAward::class);
    }

    /**
     * @return HasMany<AchievementVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(AchievementVariant::class);
    }
}
