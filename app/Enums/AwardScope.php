<?php

namespace App\Enums;

/**
 * What an award is decided over: one session, a race weekend or a season.
 */
enum AwardScope: string
{
    case Session = 'session';
    case Weekend = 'weekend';
    case Season = 'season';
}
