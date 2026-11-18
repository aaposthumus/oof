<?php

namespace App\Events;

use App\Models\NicknameEvolution;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A driver's nickname evolved on its effective date: the evolution carries
 * the driver, the from and to nicknames, the effective date and the award
 * behind it. Dispatched after the commit, and only for seasons from
 * achievements.events_from.
 */
class NicknameEvolved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public NicknameEvolution $evolution) {}
}
