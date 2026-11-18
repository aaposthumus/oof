<?php

namespace App\Events;

use App\Models\NicknameEvolution;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The engine proposed a nickname evolution after a breakthrough. Dispatched
 * once per target nickname, after the commit, and only for seasons from
 * achievements.events_from.
 */
class NicknameEvolutionProposed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public NicknameEvolution $evolution) {}
}
