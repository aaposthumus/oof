<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\LateDetector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\RaceStarts;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * "The quiet ones": a race driver with no award at all all weekend, on any
 * shelf and in any status. Awards the engine suppressed itself still count;
 * only one a person suppressed does not. Runs on the race, after the
 * weekend's other sessions. A driver's first ever start never gets it.
 */
class NothingToSeeHereDetector implements LateDetector
{
    public function key(): string
    {
        return 'nothing_to_see_here';
    }

    public function detectAfter(SessionData $data, Collection $detections): Collection
    {
        $busyDrivers = $this->driversWithAwards($data, $detections);

        return $data->resultEntries()
            ->reject(fn (DriverEntry $entry) => $busyDrivers->contains($entry->driver_id) || RaceStarts::before($entry, $data)->isEmpty())
            ->map(fn (DriverEntry $entry) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $entry->id,
                instanceKey: 'weekend',
                eventKey: "weekend:{$entry->id}",
                evidence: Evidence::results([$entry], 'No award in any session of the weekend.'),
            ))
            ->values();
    }

    /**
     * IDs of the drivers with an award this weekend: what this session just
     * found, minus what a person suppressed, and what the other sessions hold.
     *
     * @param  Collection<int, Detection>  $detections
     * @return Collection<int, int>
     */
    private function driversWithAwards(SessionData $data, Collection $detections): Collection
    {
        $ids = Achievement::query()->where('key', '!=', $this->key())->pluck('id', 'key');
        $suppressedByPeople = AchievementAward::query()
            ->where('race_session_id', $data->session->id)
            ->where('status', AwardStatus::Suppressed)
            ->whereNotNull('decided_by')
            ->get()
            ->map(fn (AchievementAward $award) => "{$award->achievement_id}|{$award->instance_key}");

        $here = $detections
            ->filter(fn (Detection $detection) => $detection->achievementKey !== $this->key()
                && ! $suppressedByPeople->contains("{$ids->get($detection->achievementKey)}|{$detection->awardInstanceKey()}"))
            ->map(fn (Detection $detection) => $data->entry($detection->driverEntryId)?->driver_id);

        $elsewhere = AchievementAward::query()
            ->where('race_session_id', '!=', $data->session->id)
            ->whereHas('raceSession', fn ($query) => $query->where('round_id', $data->session->round_id))
            ->whereIn('achievement_id', $ids->values())
            ->whereNotNull('driver_id')
            ->where(fn ($query) => $query->where('status', '!=', AwardStatus::Suppressed)->orWhereNull('decided_by'))
            ->pluck('driver_id');

        return $here->concat($elsewhere)->filter()->unique()->values();
    }
}
