<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;

/**
 * The team stood outside the top teams on form before the round (the
 * rolling look-back of achievements.surprise; see Surprise and TeamForm).
 * Reads the fact's surprise attribute.
 */
class IsSurprise implements Predicate
{
    public function sources(): array
    {
        return [DataSource::JolpicaResults];
    }

    public function availableFrom(): int
    {
        return 1950;
    }

    public function notes(): array
    {
        return ['Team form: points per race over the last 10 Grands Prix before the round, per lasting team (team lineage); the Indianapolis 500 never counts.'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        return $fact->get('surprise') !== null;
    }
}
