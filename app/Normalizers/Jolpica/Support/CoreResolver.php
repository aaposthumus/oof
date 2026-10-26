<?php

namespace App\Normalizers\Jolpica\Support;

use App\Enums\SessionType;
use App\Models\Circuit;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Normalizers\MissingDependencyException;

/**
 * Finds or creates core rows from the objects embedded in Jolpica payloads
 * (Race, Circuit, Driver, Constructor). Every method is idempotent, and rows
 * already resolved during this run are served from memory.
 */
class CoreResolver
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(
        private RoundNameResolver $roundNames,
        private TeamResolver $teams,
    ) {}

    public function flush(): void
    {
        $this->cache = [];
    }

    public function season(int $year, ?string $wikipediaUrl = null): Season
    {
        $season = $this->remember("season:{$year}", fn () => Season::firstOrCreate(['year' => $year]));

        if ($wikipediaUrl !== null && $season->wikipedia_url !== $wikipediaUrl) {
            $season->update(['wikipedia_url' => $wikipediaUrl]);
        }

        return $season;
    }

    /**
     * @param  array<string, mixed>  $circuit  A Jolpica Circuit object
     */
    public function circuit(array $circuit): Circuit
    {
        return $this->remember("circuit:{$circuit['circuitId']}", fn () => Circuit::updateOrCreate(
            ['jolpica_circuit_id' => $circuit['circuitId']],
            [
                'name' => $circuit['circuitName'],
                'locality' => $circuit['Location']['locality'] ?? null,
                'country' => $circuit['Location']['country'] ?? null,
                'latitude' => $circuit['Location']['lat'] ?? null,
                'longitude' => $circuit['Location']['long'] ?? null,
                'wikipedia_url' => $circuit['url'] ?? null,
            ],
        ));
    }

    /**
     * @param  array<string, mixed>  $race  A Jolpica Race object
     */
    public function round(array $race): Round
    {
        $year = (int) $race['season'];
        $number = (int) $race['round'];

        return $this->remember("round:{$year}:{$number}", fn () => Round::updateOrCreate(
            ['season_id' => $this->season($year)->id, 'round_number' => $number],
            [
                ...$this->roundNames->resolve($race['raceName'], $year, $number),
                'circuit_id' => $this->circuit($race['Circuit'])->id,
                'jolpica_race_name' => $race['raceName'],
                'date' => $race['date'],
                'time' => self::utcTime($race['time'] ?? null),
                'wikipedia_url' => $race['url'] ?? null,
            ],
        ));
    }

    /**
     * Look up a round that must already exist, for payloads without an embedded Race.
     */
    public function existingRound(int $year, int $number): Round
    {
        return $this->remember("round:{$year}:{$number}", function () use ($year, $number) {
            return Round::query()
                ->whereRelation('season', 'year', $year)
                ->where('round_number', $number)
                ->first() ?? throw new MissingDependencyException("Round {$year}-{$number} is not normalized yet.");
        });
    }

    public function raceSession(Round $round, SessionType $type, ?string $date = null, ?string $time = null): RaceSession
    {
        $session = $this->remember("session:{$round->id}:{$type->value}", fn () => RaceSession::firstOrCreate(
            ['round_id' => $round->id, 'type' => $type],
        ));

        if ($date !== null) {
            $session->fill(['scheduled_date' => $date, 'scheduled_time' => self::utcTime($time)])->save();
        }

        return $session;
    }

    /**
     * @param  array<string, mixed>  $driver  A Jolpica Driver object
     */
    public function driver(array $driver): Driver
    {
        return $this->remember("driver:{$driver['driverId']}", fn () => Driver::updateOrCreate(
            ['jolpica_driver_id' => $driver['driverId']],
            [
                'given_name' => $driver['givenName'],
                'family_name' => $driver['familyName'],
                'code' => $driver['code'] ?? null,
                'permanent_number' => $driver['permanentNumber'] ?? null,
                'date_of_birth' => $driver['dateOfBirth'] ?? null,
                'nationality' => $driver['nationality'] ?? null,
                'wikipedia_url' => $driver['url'] ?? null,
            ],
        ));
    }

    /**
     * @param  array<string, mixed>  $constructor  A Jolpica Constructor object
     */
    public function seasonTeam(Season $season, array $constructor): SeasonTeam
    {
        $constructorId = $constructor['constructorId'];

        return $this->remember("season_team:{$season->year}:{$constructorId}", function () use ($season, $constructor, $constructorId) {
            $team = Team::firstOrCreate(
                ['slug' => $this->teams->slugFor($constructorId, $season->year)],
                ['name' => $constructor['name']],
            );

            $seasonTeam = SeasonTeam::updateOrCreate(
                ['season_id' => $season->id, 'jolpica_constructor_id' => $constructorId],
                ['team_id' => $team->id, 'name' => $constructor['name'], 'nationality' => $constructor['nationality'] ?? null],
            );

            $isLatestSeason = ! $team->seasonTeams()
                ->whereRelation('season', 'year', '>', $season->year)
                ->exists();

            if ($isLatestSeason && $team->name !== $constructor['name']) {
                $team->update(['name' => $constructor['name']]);
            }

            return $seasonTeam;
        });
    }

    public function driverEntry(RaceSession $session, Driver $driver, SeasonTeam $seasonTeam, ?int $carNumber): DriverEntry
    {
        $entry = DriverEntry::query()
            ->where('race_session_id', $session->id)
            ->where('driver_id', $driver->id)
            ->where('car_number', $carNumber)
            ->first();

        if ($entry === null) {
            return DriverEntry::create([
                'race_session_id' => $session->id,
                'driver_id' => $driver->id,
                'season_team_id' => $seasonTeam->id,
                'car_number' => $carNumber,
            ]);
        }

        if ($entry->season_team_id !== $seasonTeam->id) {
            $entry->update(['season_team_id' => $seasonTeam->id]);
        }

        return $entry;
    }

    /**
     * Race entries of a session keyed by Jolpica driver ID. A driver with more
     * than one entry (a 1950s shared drive) maps to null, as it is ambiguous.
     *
     * @return array<string, int|null>
     */
    public function entryIdsByJolpicaDriver(RaceSession $session): array
    {
        $entries = DriverEntry::query()
            ->join('drivers', 'drivers.id', '=', 'driver_entries.driver_id')
            ->where('race_session_id', $session->id)
            ->get(['driver_entries.id', 'drivers.jolpica_driver_id']);

        return $entries->groupBy('jolpica_driver_id')
            ->map(fn ($group) => $group->count() === 1 ? $group->first()->id : null)
            ->all();
    }

    /**
     * Jolpica times are UTC with a trailing "Z", e.g. "07:00:00Z".
     */
    private static function utcTime(?string $time): ?string
    {
        return $time === null ? null : rtrim($time, 'Z');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $resolve
     * @return T
     */
    private function remember(string $key, callable $resolve): mixed
    {
        return $this->cache[$key] ??= $resolve();
    }
}
