<?php

namespace App\Excavation\Support;

use Illuminate\Support\Collection;

/**
 * Every race result in history in date order, loaded once, with the career
 * facts excavators need: start numbers, wins, podiums and shared cars.
 * Careers always span the full history; excavators filter only what they
 * return by DigParameters.
 *
 * Each row has: round_id, season_id, year, round_number, date, circuit_id,
 * race_session_id, driver_id, driver_name, date_of_birth, season_team_id,
 * car_number, grid, position, position_text, status, points (float),
 * laps_completed, time_millis, is_disqualified, is_start, start_number
 * (null for a withdrawal), starts_before, is_win, is_podium, is_shared_car.
 */
class CareerTimeline
{
    /**
     * @var Collection<int, object>|null
     */
    private ?Collection $rows = null;

    /**
     * @return Collection<int, object>
     */
    public function rows(): Collection
    {
        return $this->rows ??= $this->load();
    }

    /**
     * @return Collection<int, Collection<int, object>> Keyed by round id
     */
    public function byRound(): Collection
    {
        return $this->rows()->groupBy('round_id');
    }

    /**
     * @return Collection<int, object>
     */
    private function load(): Collection
    {
        $rows = RaceResultQuery::make()
            ->join('drivers', 'drivers.id', '=', 'driver_entries.driver_id')
            ->orderBy('rounds.date')
            ->orderBy('rounds.round_number')
            ->orderBy('results.classification_order')
            ->select([
                'rounds.id as round_id', 'rounds.season_id', 'seasons.year', 'rounds.round_number', 'rounds.date', 'rounds.circuit_id',
                'driver_entries.race_session_id', 'driver_entries.driver_id', 'driver_entries.season_team_id', 'driver_entries.car_number',
                'drivers.given_name', 'drivers.family_name', 'drivers.date_of_birth',
                'results.grid', 'results.position', 'results.position_text', 'results.status', 'results.points',
                'results.laps_completed', 'results.time_millis',
            ])
            ->get();

        $carDrivers = $rows->countBy(fn (object $row) => $this->carKey($row));
        $starts = [];
        $lastStartRound = [];

        return $rows->map(function (object $row) use ($carDrivers, &$starts, &$lastStartRound) {
            foreach (['round_id', 'season_id', 'year', 'round_number', 'circuit_id', 'race_session_id', 'driver_id', 'season_team_id'] as $key) {
                $row->{$key} = (int) $row->{$key};
            }

            foreach (['car_number', 'grid', 'position', 'laps_completed', 'time_millis'] as $key) {
                $row->{$key} = $row->{$key} === null ? null : (int) $row->{$key};
            }

            $row->points = (float) $row->points;
            $row->driver_name = "{$row->given_name} {$row->family_name}";
            $row->is_disqualified = RaceStatuses::isDisqualified($row->status);
            $row->is_start = $row->position_text !== 'W';
            $row->is_win = $row->position === 1 && ! $row->is_disqualified;
            $row->is_podium = $row->position !== null && $row->position <= 3 && ! $row->is_disqualified;
            $row->is_shared_car = $row->car_number !== null && $carDrivers[$this->carKey($row)] > 1;

            // A driver in two cars of one race (1950s relief drives) starts once.
            $starts[$row->driver_id] ??= 0;
            $row->starts_before = $starts[$row->driver_id] - (($lastStartRound[$row->driver_id] ?? null) === $row->round_id ? 1 : 0);
            $row->start_number = null;

            if ($row->is_start) {
                if (($lastStartRound[$row->driver_id] ?? null) !== $row->round_id) {
                    $starts[$row->driver_id]++;
                    $lastStartRound[$row->driver_id] = $row->round_id;
                }

                $row->start_number = $starts[$row->driver_id];
            }

            return $row;
        })->values();
    }

    private function carKey(object $row): string
    {
        return $row->race_session_id.':'.$row->season_team_id.':'.$row->car_number;
    }
}
