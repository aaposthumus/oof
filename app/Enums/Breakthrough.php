<?php

namespace App\Enums;

/**
 * What makes the engine propose a nickname evolution.
 */
enum Breakthrough: string
{
    case FirstPodium = 'first_podium';
    case FirstWin = 'first_win';
    case StrongFirstSeason = 'strong_first_season';

    public function label(): string
    {
        return match ($this) {
            self::FirstPodium => 'First podium',
            self::FirstWin => 'First win',
            self::StrongFirstSeason => 'Strong first season',
        };
    }
}
