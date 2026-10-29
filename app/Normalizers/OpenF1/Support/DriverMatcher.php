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
 * A qualifying car Jolpica has no entry for (a driver who set no time) gets
 * one, with the team of the driver's race entry in the round.
 *
 * Practice drivers who never raced are practice_only rather than unmatched,
 * and a car OpenF1 lists with neither a team nor a name is not_in_session:
 * a support series car that leaked into the session's drivers.
 */
class DriverMatcher
{
    /**
     * @param  array<string, string|null>  $overrides  Jolpica driver IDs keyed by "year:driver_number"
     * @param  list<array{name: string, constructor: string, from?: int, to?: int}>  $teams  OpenF1 team names mapped to Jolpica constructor IDs
     * @param  list<string>  $practiceOnlyDrivers  Names of drivers who drove in practice but never raced
     */
    public function __construct(
        private CoreResolver $core,
        private array $overrides,
        private array $teams,
        private array $practiceOnlyDrivers = [],
    ) {}

    /**
     * @param  array<string, mixed>  $row  An OpenF1 driver
     */
    public function match(RaceSession $session, array $row): DriverMatch
    {
        $year = $session->round->season->year;
        $number = (int) $row['driver_number'];

        if (($row['team_name'] ?? null) === null && ($row['first_name'] ?? null) === null && ($row['last_name'] ?? null) === null) {
            return new DriverMatch(OpenF1MatchStatus::NotInSession, note: 'OpenF1 lists this car without a team or name: not an F1 entry.');
        }

        $roundEntries = $this->roundEntries($session);

        [$driver, $problem] = $this->driver($year, $number, $row, $roundEntries);

        if ($driver === null) {
            if ($problem[0] === OpenF1MatchStatus::Unmatched && $session->type->isPractice() && NameMatcher::isAnyOf($row, $this->practiceOnlyDrivers)) {
                return new DriverMatch(OpenF1MatchStatus::PracticeOnly, note: "{$row['full_name']} is in openf1.practice_only_drivers.");
            }

            return new DriverMatch($problem[0], note: $problem[1]);
        }

        if ($session->type->hasJolpicaEntries()) {
            return $this->jolpicaEntry($session, $driver, $number, $roundEntries);
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

    /**
     * @param  Collection<int, DriverEntry>  $roundEntries
     */
    private function jolpicaEntry(RaceSession $session, Driver $driver, int $number, Collection $roundEntries): DriverMatch
    {
        $entries = DriverEntry::query()
            ->with('seasonTeam')
            ->where('race_session_id', $session->id)
            ->where('driver_id', $driver->id)
            ->get();
        $entry = $entries->firstWhere('car_number', $number) ?? ($entries->count() === 1 ? $entries->first() : null);

        $missing = "Jolpica has no {$session->type->value} entry for {$driver->jolpica_driver_id} in car {$number}";
        $created = "{$missing}; created with the team of the driver's race entry.";

        if ($entry !== null) {
            // Jolpica's qualifying entries come with a result; one without was created here.
            return $session->type === SessionType::Qualifying && ! $entry->qualifyingResult()->exists()
                ? new DriverMatch(OpenF1MatchStatus::Created, $driver, $entry->seasonTeam, $entry, $created)
                : new DriverMatch(OpenF1MatchStatus::Matched, $driver, $entry->seasonTeam, $entry);
        }

        $raceEntry = $roundEntries
            ->where('driver_id', $driver->id)
            ->sortBy(fn (DriverEntry $entry) => $entry->raceSession->type === SessionType::Race ? 0 : 1)
            ->first();

        if ($session->type !== SessionType::Qualifying || $raceEntry === null) {
            return new DriverMatch(OpenF1MatchStatus::Unmatched, $driver, note: "{$missing}.");
        }

        $entry = $this->core->driverEntry($session, $driver, $raceEntry->seasonTeam, $number);

        return new DriverMatch(OpenF1MatchStatus::Created, $driver, $raceEntry->seasonTeam, $entry, $created);
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
            ->with(['driver', 'seasonTeam', 'raceSession'])
            ->whereHas('raceSession', fn ($sessions) => $sessions
                ->where('round_id', $session->round_id)
                ->whereIn('type', [SessionType::Race, SessionType::Qualifying, SessionType::Sprint]))
            ->get();
    }
}
