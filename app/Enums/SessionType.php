<?php

namespace App\Enums;

enum SessionType: string
{
    case Race = 'race';
    case Qualifying = 'qualifying';
    case Sprint = 'sprint';
    case SprintQualifying = 'sprint_qualifying';
    case Practice1 = 'practice_1';
    case Practice2 = 'practice_2';
    case Practice3 = 'practice_3';

    /**
     * Whether Jolpica has this session's entries. OpenF1 creates the entries
     * of the other sessions and links to Jolpica's for these.
     */
    public function hasJolpicaEntries(): bool
    {
        return in_array($this, [self::Race, self::Qualifying, self::Sprint], true);
    }

    /**
     * Where the session falls in a race weekend, practice first and the race
     * last; sprint weekends run sprint qualifying and the sprint before qualifying.
     */
    public function weekendOrder(): int
    {
        return match ($this) {
            self::Practice1 => 0,
            self::Practice2 => 1,
            self::Practice3 => 2,
            self::SprintQualifying => 3,
            self::Sprint => 4,
            self::Qualifying => 5,
            self::Race => 6,
        };
    }

    public function isPractice(): bool
    {
        return in_array($this, [self::Practice1, self::Practice2, self::Practice3], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Race => 'Race',
            self::Qualifying => 'Qualifying',
            self::Sprint => 'Sprint',
            self::SprintQualifying => 'Sprint qualifying',
            self::Practice1 => 'FP1',
            self::Practice2 => 'FP2',
            self::Practice3 => 'FP3',
        };
    }
}
