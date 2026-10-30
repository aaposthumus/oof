<?php

namespace App\Enums;

/**
 * How sure an award is: high for a penalty or flag naming one car, medium
 * for involvement in an incident, low for anything inferred.
 */
enum AwardConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
