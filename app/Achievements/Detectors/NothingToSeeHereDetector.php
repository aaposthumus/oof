<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\LateDetector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Enums\AchievementShelf;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * "The quiet ones": a race driver who unlocked nothing on the OOF shelf all
 * weekend. Runs on the race, after the weekend's other sessions.
 */
class NothingToSeeHereDetector implements LateDetector
{
    public function key(): string
    {
        return 'nothing_to_see_here';
    }

    public function detectAfter(SessionData $data, Collection $detections): Collection
    {
        $oofKeys = Achievement::query()->where('shelf', AchievementShelf::Oof)->where('key', '!=', $this->key())->pluck('key');
        $busyHere = $detections->filter(fn (Detection $detection) => $oofKeys->contains($detection->achievementKey))->pluck('driverEntryId');
        $busyElsewhere = AchievementAward::query()
            ->where('race_session_id', '!=', $data->session->id)
            ->where('status', '!=', AwardStatus::Suppressed)
            ->whereHas('raceSession', fn ($query) => $query->where('round_id', $data->session->round_id))
            ->whereHas('achievement', fn ($query) => $query->whereIn('key', $oofKeys))
            ->pluck('driver_id');

        return $data->resultEntries()
            ->reject(fn (DriverEntry $entry) => $busyHere->contains($entry->id) || $busyElsewhere->contains($entry->driver_id))
            ->map(fn (DriverEntry $entry) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $entry->id,
                instanceKey: 'weekend',
                eventKey: "weekend:{$entry->id}",
                evidence: Evidence::results([$entry], 'No OOF achievement in any session of the weekend.'),
            ))
            ->values();
    }
}
