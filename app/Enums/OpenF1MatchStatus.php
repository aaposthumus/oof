<?php

namespace App\Enums;

/**
 * How an OpenF1 meeting, session or session driver relates to our core rows.
 */
enum OpenF1MatchStatus: string
{
    case Matched = 'matched';
    case Created = 'created';
    case Cancelled = 'cancelled';
    case NotARound = 'not_a_round';
    case PracticeOnly = 'practice_only';
    case NotInSession = 'not_in_session';
    case Unmatched = 'unmatched';
    case Conflict = 'conflict';

    /**
     * Whether this status needs a look: it was neither linked nor expected to stay unlinked.
     */
    public function needsAttention(): bool
    {
        return in_array($this, [self::Unmatched, self::Conflict], true);
    }
}
