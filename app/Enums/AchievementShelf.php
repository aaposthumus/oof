<?php

namespace App\Enums;

/**
 * Where an achievement is shown. Only the OOF shelf earns OOFscore points.
 * The Oofies are the season's own awards, without tiers or points.
 */
enum AchievementShelf: string
{
    case Oof = 'oof';
    case Glory = 'glory';
    case SafetyCar = 'safety_car';
    case Oofies = 'oofies';

    public function label(): string
    {
        return match ($this) {
            self::Oof => 'OOF',
            self::Glory => 'Glory',
            self::SafetyCar => 'Safety Car',
            self::Oofies => 'Oofies',
        };
    }

    /**
     * The first season the shelf counts (achievements.shelf_from): before it,
     * its awards score 0, stay out of the OOFscore, the Oofies and the stamp
     * books, and are never published automatically.
     */
    public function firstSeason(): int
    {
        return (int) (config("achievements.shelf_from.{$this->value}") ?? config('achievements.first_season'));
    }

    public function countsIn(int $year): bool
    {
        return $year >= $this->firstSeason();
    }
}
