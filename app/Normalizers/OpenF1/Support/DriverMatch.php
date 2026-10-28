<?php

namespace App\Normalizers\OpenF1\Support;

use App\Enums\OpenF1MatchStatus;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\SeasonTeam;

final readonly class DriverMatch
{
    public function __construct(
        public OpenF1MatchStatus $status,
        public ?Driver $driver = null,
        public ?SeasonTeam $seasonTeam = null,
        public ?DriverEntry $entry = null,
        public ?string $note = null,
    ) {}
}
