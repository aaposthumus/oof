<?php

namespace App\Enums;

enum MomentCandidateStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Blocked = 'blocked';

    /**
     * Whether a reviewer set this status, so excavation must never change it.
     */
    public function isReviewed(): bool
    {
        return $this === self::Accepted || $this === self::Rejected;
    }
}
