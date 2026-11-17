<?php

namespace App\Achievements\Facts;

/**
 * Where a fact or predicate reads its data from.
 */
enum DataSource: string
{
    case JolpicaResults = 'jolpica_results';
    case JolpicaQualifying = 'jolpica_qualifying';
    case OpenF1RaceControl = 'openf1_race_control';
    case OpenF1Laps = 'openf1_laps';
    case OpenF1Pit = 'openf1_pit';
    case OpenF1Positions = 'openf1_positions';

    public function label(): string
    {
        return match ($this) {
            self::JolpicaResults => 'Jolpica results',
            self::JolpicaQualifying => 'Jolpica qualifying',
            self::OpenF1RaceControl => 'OpenF1 race control',
            self::OpenF1Laps => 'OpenF1 laps',
            self::OpenF1Pit => 'OpenF1 pit',
            self::OpenF1Positions => 'OpenF1 positions',
        };
    }
}
