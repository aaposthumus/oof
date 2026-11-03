<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\RaceStarts;
use App\Enums\AwardConfidence;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * Called up to race as a replacement: a first race for a team that already
 * raced earlier that season. A driver with no start earlier that season is a
 * genuine call-up (high confidence). One who already raced for another team
 * that season is a seat swap, which is low confidence and always reviewed.
 */
class SpareTyreDetector implements Detector
{
    public function key(): string
    {
        return 'spare_tyre';
    }

    public function detect(SessionData $data): Collection
    {
        $round = $data->session->round;
        $earlier = DriverEntry::query()
            ->whereHas('raceSession', fn ($query) => $query
                ->where('type', SessionType::Race)
                ->whereHas('round', fn ($query) => $query->where('season_id', $round->season_id)->where('round_number', '<', $round->round_number)))
            ->get(['driver_id', 'season_team_id']);

        return $data->resultEntries()
            ->filter(fn (DriverEntry $entry) => $earlier->contains('season_team_id', $entry->season_team_id)
                && ! $earlier->contains(fn (DriverEntry $other) => $other->season_team_id === $entry->season_team_id && $other->driver_id === $entry->driver_id))
            ->map(function (DriverEntry $entry) use ($data) {
                $isSeatSwap = RaceStarts::before($entry, $data, thisSeasonOnly: true)->isNotEmpty();
                $team = $entry->seasonTeam->name;

                return new Detection(
                    achievementKey: $this->key(),
                    driverEntryId: $entry->id,
                    instanceKey: 'call_up',
                    eventKey: "call_up:{$entry->id}",
                    evidence: Evidence::results([$entry], $isSeatSwap
                        ? "First race for {$team} this season, but the driver raced for another team earlier: a seat swap."
                        : "First race start of the season, for {$team}, after the team raced earlier rounds."),
                    confidence: $isSeatSwap ? AwardConfidence::Low : AwardConfidence::High,
                    detail: $team,
                );
            })
            ->values();
    }
}
