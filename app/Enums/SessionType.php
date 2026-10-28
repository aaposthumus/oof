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

    public function isPractice(): bool
    {
        return in_array($this, [self::Practice1, self::Practice2, self::Practice3], true);
    }
}
