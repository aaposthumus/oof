<?php

namespace App\Enums;

/**
 * The side of an Oofie: glory or oof. It picks the art folder and the
 * stamp's theme, and the shelf whose first season it counts from: a
 * glory-side Oofie counts like the glory shelf, an OOF-side one like the
 * Oofies.
 */
enum AchievementSide: string
{
    case Glory = 'glory';
    case Oof = 'oof';

    public function label(): string
    {
        return match ($this) {
            self::Glory => 'Glory',
            self::Oof => 'OOF',
        };
    }

    /**
     * The shelf whose first season (achievements.shelf_from) it counts from.
     */
    public function countsLike(): AchievementShelf
    {
        return match ($this) {
            self::Glory => AchievementShelf::Glory,
            self::Oof => AchievementShelf::Oofies,
        };
    }
}
