<?php

namespace App\Enums;

/**
 * Who receives an award: a driver, a team or the Safety Car.
 */
enum RecipientType: string
{
    case Driver = 'driver';
    case Team = 'team';
    case SafetyCar = 'safety_car';

    public function label(): string
    {
        return match ($this) {
            self::Driver => 'Driver',
            self::Team => 'Team',
            self::SafetyCar => 'Safety Car',
        };
    }
}
