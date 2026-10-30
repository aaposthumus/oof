<?php

namespace App\Enums;

/**
 * Where an achievement is shown. Only the OOF shelf earns OOFscore points.
 */
enum AchievementShelf: string
{
    case Oof = 'oof';
    case Glory = 'glory';
    case SafetyCar = 'safety_car';

    public function label(): string
    {
        return match ($this) {
            self::Oof => 'OOF',
            self::Glory => 'Glory',
            self::SafetyCar => 'Safety Car',
        };
    }
}
