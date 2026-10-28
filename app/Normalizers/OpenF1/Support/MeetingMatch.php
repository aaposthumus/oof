<?php

namespace App\Normalizers\OpenF1\Support;

use App\Enums\OpenF1MatchStatus;
use App\Models\Round;

final readonly class MeetingMatch
{
    public function __construct(
        public OpenF1MatchStatus $status,
        public ?Round $round = null,
        public ?string $note = null,
    ) {}
}
