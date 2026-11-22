<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\RaceControl\MessageClassification;
use App\RaceControl\Offence;

/**
 * Bare "FALSE START" messages (2024) that the stewards ruled a car out of
 * position, not a jump start: config/starting_position_rulings.php lists
 * them, with the FIA decision document.
 */
class StartingPositionRulings
{
    /**
     * The classification with its offence moved to the starting procedure
     * when every car it names is listed for this session; else unchanged.
     *
     * @param  list<int>  $entryIds
     */
    public static function apply(?MessageClassification $classification, array $entryIds, SessionData $data): ?MessageClassification
    {
        if ($classification?->offence !== Offence::FalseStart || $entryIds === []) {
            return $classification;
        }

        foreach ($entryIds as $entryId) {
            if (! self::isRuledOutOfPosition($data, $data->entries->get($entryId)?->driver?->jolpica_driver_id)) {
                return $classification;
            }
        }

        return new MessageClassification($classification->type, $classification->detail, $classification->reason, Offence::StartingProcedure);
    }

    private static function isRuledOutOfPosition(SessionData $data, ?string $jolpicaDriverId): bool
    {
        return $jolpicaDriverId !== null && collect(config('starting_position_rulings.rulings'))->contains(
            fn (array $ruling) => $ruling['season'] === $data->year()
                && $ruling['round'] === $data->session->round->round_number
                && $ruling['session'] === $data->type()->value
                && $ruling['driver'] === $jolpicaDriverId,
        );
    }
}
