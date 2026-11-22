<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one definition of pole (decided by the owner on 21 and 22 November
 * 2026), read by Early Bird and Plot Twist (the qualifying order), took_pole
 * (Clean Sweep, Peaked Too Soon) and First Time for Everything:
 *
 * - A round with qualifying data: the driver P1 in qualifying.
 * - Without (every round before 1994 and most of 1996 to 2002, D10): the
 *   driver on grid 1 of the race. The pole is earned before the race, so a
 *   car its driver left for another (D8) still holds it; a plain withdrawal
 *   (D9) never does. When several drivers are on grid 1 (a shared car, or
 *   a grid error), config/official_poles.php names the one the official
 *   record credits; an unlisted tie gives no pole.
 */
class Poles
{
    /**
     * @var array<string, array<int, true>>
     */
    private array $earlier = [];

    /**
     * The round's pole driver, from any session of the round.
     */
    public static function driverOf(SessionData $data): ?int
    {
        $qualifying = $data->roundSession(SessionType::Qualifying);
        $qualifyingPole = $qualifying?->entries->first(fn (DriverEntry $entry) => $entry->qualifyingResult?->position === 1);

        if (self::hasQualifyingData($data)) {
            return $qualifyingPole?->driver_id;
        }

        $race = $data->roundSession(SessionType::Race);

        return $race === null ? null : $race->entry(self::gridPoleEntryId($race))?->driver_id;
    }

    public static function hasQualifyingData(SessionData $data): bool
    {
        return (bool) $data->roundSession(SessionType::Qualifying)?->entries->contains(fn (DriverEntry $entry) => $entry->qualifyingResult !== null);
    }

    /**
     * In a race without qualifying data for its round, the one car credited
     * with pole: the grid 1 car of the pole driver (the car they started in,
     * even if they left it), else none.
     */
    public static function gridPoleEntryId(SessionData $race): ?int
    {
        $withdrawn = $race->withdrawnEntryIds();
        $onPole = $race->resultEntries()
            ->filter(fn (DriverEntry $entry) => (int) $entry->result->grid === 1 && ! in_array($entry->id, $withdrawn, true))
            ->values();
        $driverId = self::resolve(
            $onPole->map(fn (DriverEntry $entry) => ['driver_id' => $entry->driver_id, 'jolpica_driver_id' => $entry->driver->jolpica_driver_id]),
            $race->year(),
            $race->session->round->round_number,
        );

        $cars = $onPole->where('driver_id', $driverId);
        $leftCars = $race->leftCarEntryIds();

        return ($cars->first(fn (DriverEntry $entry) => ! in_array($entry->id, $leftCars, true)) ?? $cars->sortBy('id')->first())?->id;
    }

    /**
     * Whether the driver took a pole in a round before this one.
     */
    public function hadPoleBefore(SessionData $data, int $driverId): bool
    {
        $date = $data->session->round->date->toDateString();

        return isset(($this->earlier[$date] ??= $this->polesBefore($date))[$driverId]);
    }

    /**
     * The drivers on grid 1, reduced to the pole driver: the only one, or
     * with several the one the official list names, else nobody.
     *
     * @param  Collection<int, array{driver_id: int, jolpica_driver_id: string|null}>  $onPole
     */
    private static function resolve(Collection $onPole, int $season, int $round): ?int
    {
        $drivers = $onPole->pluck('driver_id')->unique()->values();

        if ($drivers->count() <= 1) {
            return $drivers->first();
        }

        $listed = collect(config('official_poles.rounds'))->first(fn (array $pole) => $pole['season'] === $season && $pole['round'] === $round);

        return $listed === null ? null : $onPole->firstWhere('jolpica_driver_id', $listed['driver'])['driver_id'] ?? null;
    }

    /**
     * Every pole driver of the rounds before a date, by the same rules.
     *
     * @return array<int, true>
     */
    private function polesBefore(string $date): array
    {
        $qualifying = DB::table('qualifying_results')
            ->join('driver_entries', 'driver_entries.id', '=', 'qualifying_results.driver_entry_id')
            ->join('race_sessions', 'race_sessions.id', '=', 'driver_entries.race_session_id')
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->where('race_sessions.type', SessionType::Qualifying->value)
            ->where('qualifying_results.position', 1)
            ->where('rounds.date', '<', $date)
            ->pluck('driver_entries.driver_id');

        $grid = DB::table('results')
            ->join('driver_entries', 'driver_entries.id', '=', 'results.driver_entry_id')
            ->join('drivers', 'drivers.id', '=', 'driver_entries.driver_id')
            ->join('race_sessions', 'race_sessions.id', '=', 'driver_entries.race_session_id')
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->join('seasons', 'seasons.id', '=', 'rounds.season_id')
            ->where('race_sessions.type', SessionType::Race->value)
            ->where('results.grid', 1)
            ->where('rounds.date', '<', $date)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('race_sessions as qualifying')
                ->join('driver_entries as qualifying_entries', 'qualifying_entries.race_session_id', '=', 'qualifying.id')
                ->join('qualifying_results as classified', 'classified.driver_entry_id', '=', 'qualifying_entries.id')
                ->whereColumn('qualifying.round_id', 'rounds.id')
                ->where('qualifying.type', SessionType::Qualifying->value))
            ->where(fn ($query) => $query
                ->where('seasons.year', '>=', (int) config('achievements.plain_withdrawals_from'))
                ->orWhere('results.position_text', '!=', 'W')
                ->orWhere('results.status', '!=', 'Withdrew'))
            ->get(['driver_entries.driver_id', 'drivers.jolpica_driver_id', 'seasons.year', 'rounds.round_number', 'rounds.id as round_id'])
            ->groupBy('round_id')
            ->map(fn (Collection $rows) => self::resolve(
                $rows->map(fn (object $row) => ['driver_id' => (int) $row->driver_id, 'jolpica_driver_id' => $row->jolpica_driver_id]),
                (int) $rows->first()->year,
                (int) $rows->first()->round_number,
            ))
            ->filter();

        return $qualifying->concat($grid)->mapWithKeys(fn ($driverId) => [(int) $driverId => true])->all();
    }
}
