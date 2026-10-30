<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * Who went out where in qualifying (Q1-Q3, or SQ1-SQ3 in sprint qualifying),
 * by classified position: ten cars reach the last phase, and the first phase
 * knocks out half the rest (5 of 20 cars, 6 of 22). A car can go through and
 * then not set a time, so times alone do not tell.
 */
class QualifyingPhases
{
    private const LAST_PHASE_CARS = 10;

    /**
     * @var Collection<int, DriverEntry>
     */
    private Collection $entries;

    public function __construct(SessionData $data)
    {
        $this->entries = $data->entries->filter(fn (DriverEntry $entry) => $entry->qualifyingResult !== null)->values();
    }

    public static function column(int $phase): string
    {
        return "q{$phase}_millis";
    }

    public function reached(DriverEntry $entry, int $phase): bool
    {
        $position = $entry->qualifyingResult->position;

        return match ($phase) {
            1 => true,
            2 => $position !== null && $position <= self::LAST_PHASE_CARS + (int) ceil(($this->entries->count() - self::LAST_PHASE_CARS) / 2),
            default => $position !== null && $position <= self::LAST_PHASE_CARS,
        };
    }

    /**
     * Cars knocked out in a phase (1 or 2) that set a time in it, fastest first.
     *
     * @return Collection<int, DriverEntry>
     */
    public function knockedOut(int $phase): Collection
    {
        return $this->entries
            ->filter(fn (DriverEntry $entry) => $this->reached($entry, $phase) && ! $this->reached($entry, $phase + 1) && $entry->qualifyingResult->{self::column($phase)} !== null)
            ->sortBy(fn (DriverEntry $entry) => $entry->qualifyingResult->{self::column($phase)})
            ->values();
    }

    /**
     * The car that took the last place through to the next phase: the slowest
     * in the phase among those who went through.
     */
    public function lastThrough(int $phase): ?DriverEntry
    {
        return $this->entries
            ->filter(fn (DriverEntry $entry) => $this->reached($entry, $phase + 1) && $entry->qualifyingResult->{self::column($phase)} !== null)
            ->sortByDesc(fn (DriverEntry $entry) => $entry->qualifyingResult->{self::column($phase)})
            ->first();
    }

    public static function seconds(int $millis): string
    {
        return sprintf('%d:%06.3f', intdiv($millis, 60_000), ($millis % 60_000) / 1000);
    }
}
