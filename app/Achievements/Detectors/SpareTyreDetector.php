<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Enums\AwardConfidence;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * Called up to race as a replacement: a first race for a team that already
 * raced earlier that season. This also catches seat swaps, which are not
 * call-ups, so it is low confidence and always reviewed.
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
            ->map(fn (DriverEntry $entry) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $entry->id,
                instanceKey: 'call_up',
                eventKey: "call_up:{$entry->id}",
                evidence: Evidence::results([$entry], "First race for {$entry->seasonTeam->name} this season, after the team raced earlier rounds. A seat swap also looks like this."),
                confidence: AwardConfidence::Low,
                detail: $entry->seasonTeam->name,
            ))
            ->values();
    }
}
