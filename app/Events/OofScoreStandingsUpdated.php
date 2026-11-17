<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A season's OOFscore standings changed. Dispatched after the commit, and
 * only for seasons from achievements.events_from.
 */
class OofScoreStandingsUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $changedRoundIds  Rounds whose standings differ from before the rebuild
     */
    public function __construct(
        public int $seasonId,
        public array $changedRoundIds,
    ) {}
}
