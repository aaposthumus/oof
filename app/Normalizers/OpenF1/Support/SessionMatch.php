<?php

namespace App\Normalizers\OpenF1\Support;

use App\Enums\OpenF1MatchStatus;
use App\Models\RaceSession;

final readonly class SessionMatch
{
    public function __construct(
        public OpenF1MatchStatus $status,
        public ?RaceSession $raceSession = null,
        public ?string $note = null,
    ) {}
}
