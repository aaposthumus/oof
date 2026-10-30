<?php

namespace App\Enums;

/**
 * The group an achievement is listed under in the spec. Offence and penalty
 * achievements assign fault, so they must come from a message naming one car.
 */
enum AchievementSection: string
{
    case Race = 'race';
    case Offence = 'offence';
    case AnySession = 'any';
    case Penalty = 'penalty';
    case Qualifying = 'qualifying';
    case Practice = 'practice';
    case Glory = 'glory';
    case SafetyCar = 'safety_car';

    public function label(): string
    {
        return match ($this) {
            self::Race => 'Race',
            self::Offence => 'Offence',
            self::AnySession => 'Any session',
            self::Penalty => 'Penalty',
            self::Qualifying => 'Qualifying',
            self::Practice => 'Practice',
            self::Glory => 'Glory',
            self::SafetyCar => 'Safety Car',
        };
    }

    public function assignsFault(): bool
    {
        return $this === self::Offence || $this === self::Penalty;
    }
}
