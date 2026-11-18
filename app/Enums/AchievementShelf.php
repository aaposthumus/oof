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
}
