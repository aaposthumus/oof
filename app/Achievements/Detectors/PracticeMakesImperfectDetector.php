<?php

namespace App\Achievements\Detectors;

use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\TrackLimitDeletions;
use Illuminate\Support\Collection;

/**
 * Most lap times deleted in a practice session, at least the threshold.
 * Race control only publishes practice deletions from 2026.
 */
class PracticeMakesImperfectDetector extends MostInSessionDetector
{
    public function key(): string
    {
        return 'practice_makes_imperfect';
    }

    protected function counts(SessionData $data): Collection
    {
        return TrackLimitDeletions::of($data)->countBy('entry_id');
    }

    protected function minimum(): int
    {
        return (int) config('achievements.practice_deleted_laps');
    }

    protected function detail(int $count): string
    {
        return $count === 1 ? '1 deleted lap' : "{$count} deleted laps";
    }

    protected function evidence(SessionData $data, int $entryId): array
    {
        return Evidence::messages(TrackLimitDeletions::of($data)->where('entry_id', $entryId)->pluck('message'));
    }
}
