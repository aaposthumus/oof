<?php

namespace App\Enums;

enum AchievementTier: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';

    /**
     * OOFscore points for an OOF shelf achievement of this tier.
     */
    public function points(): int
    {
        return match ($this) {
            self::Bronze => 10,
            self::Silver => 25,
            self::Gold => 50,
            self::Platinum => 100,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
