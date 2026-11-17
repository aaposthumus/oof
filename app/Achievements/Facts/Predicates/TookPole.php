<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Enums\SessionType;
use App\Models\DriverEntry;

/**
 * The driver took pole: P1 in the round's qualifying, or grid 1 when the
 * round has no qualifying classification.
 */
class TookPole implements Predicate
{
    public function sources(): array
    {
        return [DataSource::JolpicaResults, DataSource::JolpicaQualifying];
    }

    public function availableFrom(): int
    {
        return 1950;
    }

    public function notes(): array
    {
        return ['Pole from qualifying where present, otherwise grid 1.'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $entry = $data->entry($fact->entryId);
        $qualifying = $data->roundSession(SessionType::Qualifying);
        $pole = $qualifying?->entries->first(fn (DriverEntry $other) => $other->qualifyingResult?->position === 1);

        return $pole !== null ? $pole->driver_id === $entry->driver_id : $entry->result->grid === 1;
    }
}
