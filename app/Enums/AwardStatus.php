<?php

namespace App\Enums;

enum AwardStatus: string
{
    case Pending = 'pending';
    case Final = 'final';
    case Suppressed = 'suppressed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
