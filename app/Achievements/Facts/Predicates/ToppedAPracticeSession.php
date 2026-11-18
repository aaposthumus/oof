<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;

/**
 * The driver was P1 in at least one practice session of the round (OpenF1's
 * practice classification; see PracticeToppers). Reads the fact's
 * topped_practice attribute.
 */
class ToppedAPracticeSession implements Predicate
{
    public function sources(): array
    {
        return [DataSource::OpenF1SessionResults];
    }

    public function availableFrom(): int
    {
        return 2023;
    }

    public function notes(): array
    {
        return ['Practice classifications come from OpenF1 session results (from 2023).'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        return $fact->get('topped_practice') !== null;
    }
}
