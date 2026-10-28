<?php

namespace App\Normalizers\OpenF1\Support;

use App\Enums\OpenF1MatchStatus;
use App\Enums\SessionType;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use App\Models\SeasonTeam;
use App\Normalizers\Jolpica\Support\CoreResolver;
use Illuminate\Support\Collection;

/**
 * Matches a car in an OpenF1 session to our driver, team and driver entry.
 *
 * The driver is, in order: a configured override; the driver Jolpica has in
 * this car number in the same round, provided the acronym or name agrees
 * (numbers change between seasons: Doohan was 61 before 7); or the one
 * driver with this acronym and name (stand-ins and rookies). A surname alone
 * is never enough: our Crawford is Jim, not Jak.
 *
 * Race, qualifying and sprint entries are Jolpica's and are only linked;
 * entries of the other sessions are created, with the team OpenF1 names.
 */
class DriverMatcher
{
    /**
     * @param  array<string, string|null>  $overrides  Jolpica driver IDs keyed by "year:driver_number"
     * @param  list<array{name: string, constructor: string, from?: int, to?: int}>  $teams  OpenF1 team names mapped to Jolpica constructor IDs
     */
    public function __construct(
        private CoreResolver $core,
        private array $overrides,
        private array $teams,
    ) {}

    /**
     * @param  array<string, mixed>  $row  An OpenF1 driver
     */
    public function match(RaceSession $session, array $row): DriverMatch
    {
        $year = $session->round->season->year;
        $number = (int) $row['driver_number'];
        $roundEntries = $this->roundEntries($session);

        [$driver, $problem] = $this->driver($year, $number, $row, $roundEntries);

        if ($driver === null) {
            return new DriverMatch($problem[0], note: $problem[1]);
        }

        if ($session->type->hasJolpicaEntries()) {
            return $this->jolpicaEntry($session, $driver, $number);
        }

        $seasonTeam = $this->seasonTeam($session, $row['team_name'] ?? null)
            ?? $roundEntries->firstWhere('driver_id', $driver->id)?->seasonTeam;

        if ($seasonTeam === null) {
            return new DriverMatch(OpenF1MatchStatus::Unmatched, $driver, note: "Team \"{$row['team_name']}\" is not in openf1.teams for {$year}.");
        }

        $entry = $this->core->driverEntry($session, $driver, $seasonTeam, $number);

        return new DriverMatch(OpenF1MatchStatus::Matched, $driver, $seasonTeam, $entry);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<int, DriverEntry>  $roundEntries
     * @return array{0: ?Driver, 1: array{0: OpenF1MatchStatus, 1: string}|null}
     */
    private function driver(int $year, int $number, array $row, Collection $roundEntries): array
    {
        $overrideKey = "{$year}:{$number}";

        if (array_key_exists($overrideKey, $this->overrides)) {
            $driverId = $this->overrides[$overrideKey];
            $driver = $driverId === null ? null : Driver::firstWhere('jolpica_driver_id', $driverId);

            return $driver !== null ? [$driver, null] : [null, [OpenF1MatchStatus::Unmatched, "Override {$overrideKey} names no known driver."]];
        }

        $label = trim(($row['name_acronym'] ?? '?').' '.($row['full_name'] ?? ''));
        $byNumber = $roundEntries->where('car_number', $number)->pluck('driver')->unique('id');

        if ($byNumber->count() === 1) {
            $driver = $byNumber->first();

            if ($this->agrees($driver, $row)) {
                return [$driver, null];
            }

            return [null, [OpenF1MatchStatus::Conflict, "Car {$number} is {$driver->jolpica_driver_id} in this round in Jolpica, but OpenF1 lists {$label}."]];
        }

        $byAcronym = Driver::query()
            ->where('code', $row['name_acronym'] ?? '')
            ->get()
            ->filter(fn (Driver $driver) => NameMatcher::sameDriver($driver, $row))
            ->values();

        return match ($byAcronym->count()) {
            1 => [$byAcronym->first(), null],
            0 => [null, [OpenF1MatchStatus::Unmatched, "No driver {$label} (car {$number}) in Jolpica."]],
            default => [null, [OpenF1MatchStatus::Conflict, "More than one driver matches {$label}: ".$byAcronym->pluck('jolpica_driver_id')->implode(', ').'.']],
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function agrees(Driver $driver, array $row): bool
    {
        $acronym = $row['name_acronym'] ?? null;

        return ($acronym !== null && $acronym === $driver->code) || NameMatcher::sameDriver($driver, $row);
    }

    private function jolpicaEntry(RaceSession $session, Driver $driver, int $number): DriverMatch
    {
        $entries = DriverEntry::query()
            ->with('seasonTeam')
            ->where('race_session_id', $session->id)
            ->where('driver_id', $driver->id)
            ->get();
        $entry = $entries->firstWhere('car_number', $number) ?? ($entries->count() === 1 ? $entries->first() : null);

        if ($entry === null) {
            return new DriverMatch(OpenF1MatchStatus::Unmatched, $driver, note: "Jolpica has no {$session->type->value} entry for {$driver->jolpica_driver_id} in car {$number}.");
        }

        return new DriverMatch(OpenF1MatchStatus::Matched, $driver, $entry->seasonTeam, $entry);
    }

    private function seasonTeam(RaceSession $session, ?string $teamName): ?SeasonTeam
    {
        $year = $session->round->season->year;

        foreach ($this->teams as $team) {
            if ($team['name'] === $teamName && $year >= ($team['from'] ?? PHP_INT_MIN) && $year <= ($team['to'] ?? PHP_INT_MAX)) {
                return SeasonTeam::query()
                    ->where('season_id', $session->round->season_id)
                    ->where('jolpica_constructor_id', $team['constructor'])
                    ->first();
            }
        }

        return null;
    }

    /**
     * Jolpica's entries in the round, with their drivers and teams.
     *
     * @return Collection<int, DriverEntry>
     */
    private function roundEntries(RaceSession $session): Collection
    {
        return DriverEntry::query()
            ->with(['driver', 'seasonTeam'])
            ->whereHas('raceSession', fn ($sessions) => $sessions
                ->where('round_id', $session->round_id)
                ->whereIn('type', [SessionType::Race, SessionType::Qualifying, SessionType::Sprint]))
            ->get();
    }
}
