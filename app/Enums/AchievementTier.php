<?php

namespace App\Enums;

enum AchievementTier: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
