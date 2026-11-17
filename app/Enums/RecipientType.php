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
}
