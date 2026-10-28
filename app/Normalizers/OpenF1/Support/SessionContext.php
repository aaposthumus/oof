<?php

namespace App\Normalizers\OpenF1\Support;

use App\Models\RaceSession;

/**
 * The race session an OpenF1 response is about, and its cars' driver entries.
 */
final readonly class SessionContext
{
    /**
     * @param  array<int, int|null>  $entryIds  Driver entry IDs keyed by driver number; null when unmatched
     */
    public function __construct(
        public RaceSession $session,
        public array $entryIds,
    ) {}

    public function entryIdFor(?int $driverNumber): ?int
    {
        return $driverNumber === null ? null : $this->entryIds[$driverNumber] ?? null;
    }
}
