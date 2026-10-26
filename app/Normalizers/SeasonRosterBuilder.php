<?php

namespace App\Normalizers;

use App\Enums\SessionType;
use App\Models\DriverEntry;
use App\Models\Season;
use App\Models\SeasonDriver;

/**
 * Derives a season's roster from its race, sprint and qualifying entries: one
 * row per driver and team, so a mid-season switch gives two rows. Roles set by
 * hand (e.g. reserve) and rows without entries are left alone.
 */
class SeasonRosterBuilder
{
    public function rebuild(Season $season): void
    {
        $entries = DriverEntry::query()
            ->join('race_sessions', 'race_sessions.id', '=', 'driver_entries.race_session_id')
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->where('rounds.season_id', $season->id)
            ->whereIn('race_sessions.type', [SessionType::Race, SessionType::Sprint, SessionType::Qualifying])
            ->orderBy('rounds.round_number')
            ->get(['driver_entries.driver_id', 'driver_entries.season_team_id', 'driver_entries.car_number', 'rounds.round_number']);

        foreach ($entries->groupBy(fn ($entry) => "{$entry->driver_id}:{$entry->season_team_id}") as $group) {
            SeasonDriver::updateOrCreate(
                [
                    'season_id' => $season->id,
                    'driver_id' => $group->first()->driver_id,
                    'season_team_id' => $group->first()->season_team_id,
                ],
                [
                    'usual_number' => $this->mostUsedNumber($group->pluck('car_number')->all()),
                    'first_round_number' => $group->min('round_number'),
                    'last_round_number' => $group->max('round_number'),
                ],
            );
        }
    }

    /**
     * The most frequent number; on a tie, the one used most recently.
     *
     * @param  list<int|null>  $numbers  In round order
     */
    private function mostUsedNumber(array $numbers): ?int
    {
        $counts = [];

        foreach (array_filter($numbers, fn (?int $number) => $number !== null) as $number) {
            $count = ($counts[$number] ?? 0) + 1;
            unset($counts[$number]); // re-append so the array stays in order of last use
            $counts[$number] = $count;
        }

        if ($counts === []) {
            return null;
        }

        $best = max($counts);

        return array_key_last(array_filter($counts, fn (int $count) => $count === $best));
    }
}
