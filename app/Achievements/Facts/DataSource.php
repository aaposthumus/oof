<?php

namespace App\Achievements\Facts;

use App\Achievements\SessionData;
use App\Models\DriverEntry;

/**
 * Where a fact or predicate reads its data from.
 */
enum DataSource: string
{
    case JolpicaResults = 'jolpica_results';
    case JolpicaQualifying = 'jolpica_qualifying';
    case OpenF1SessionResults = 'openf1_session_results';
    case OpenF1RaceControl = 'openf1_race_control';
    case OpenF1Laps = 'openf1_laps';
    case OpenF1Pit = 'openf1_pit';
    case OpenF1Positions = 'openf1_positions';
    case Awards = 'achievement_awards';
    case OofScore = 'oofscore_standings';

    public function label(): string
    {
        return match ($this) {
            self::JolpicaResults => 'Jolpica results',
            self::JolpicaQualifying => 'Jolpica qualifying',
            self::OpenF1SessionResults => 'OpenF1 practice results',
            self::OpenF1RaceControl => 'OpenF1 race control',
            self::OpenF1Laps => 'OpenF1 laps',
            self::OpenF1Pit => 'OpenF1 pit',
            self::OpenF1Positions => 'OpenF1 positions',
            self::Awards => 'Achievement awards',
            self::OofScore => 'OOFscore standings',
        };
    }

    /**
     * Whether the source has any rows for the session. Awards and standings
     * are read per season, never per session.
     */
    public function hasRows(SessionData $data): bool
    {
        return match ($this) {
            self::JolpicaResults => $data->resultEntries()->isNotEmpty(),
            self::JolpicaQualifying => $data->entries->contains(fn (DriverEntry $entry) => $entry->qualifyingResult !== null),
            self::OpenF1SessionResults => $data->entries->contains(fn (DriverEntry $entry) => $entry->practiceResult !== null),
            self::OpenF1RaceControl => $data->messages()->isNotEmpty(),
            self::OpenF1Laps => $data->laps()->isNotEmpty(),
            self::OpenF1Pit => $data->pitVisits()->isNotEmpty(),
            self::OpenF1Positions => $data->positions()->isNotEmpty(),
            self::Awards, self::OofScore => true,
        };
    }
}
