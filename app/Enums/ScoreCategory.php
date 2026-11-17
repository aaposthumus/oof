<?php

namespace App\Enums;

/**
 * What an OOF achievement mostly reflects, which decides whether the
 * OOFscore weighs it by the team's form: the driver's own doing (never),
 * the car's pace or its reliability (by the multipliers in
 * achievements.points.multipliers), or a surprise already (never).
 */
enum ScoreCategory: string
{
    case Driver = 'driver';
    case Pace = 'pace';
    case Reliability = 'reliability';
    case Surprise = 'surprise';

    public function label(): string
    {
        return match ($this) {
            self::Driver => 'Driver: the driver\'s own doing (×1)',
            self::Pace => 'Pace: how fast the car is (weighed by team form)',
            self::Reliability => 'Reliability: how reliable the car is (weighed by team form)',
            self::Surprise => 'Surprise: already about form (×1)',
        };
    }
}
