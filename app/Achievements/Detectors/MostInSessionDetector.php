<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use Illuminate\Support\Collection;

/**
 * The race driver with the most of something in a practice session, with at
 * least the minimum. A shared top spot gives no award. Practice-only drivers
 * and FP1 rookies do not count.
 */
abstract class MostInSessionDetector implements Detector
{
    /**
     * Counts per driver entry ID.
     *
     * @return Collection<int, int>
     */
    abstract protected function counts(SessionData $data): Collection;

    abstract protected function detail(int $count): string;

    protected function minimum(): int
    {
        return 1;
    }

    public function detect(SessionData $data): Collection
    {
        $raceDrivers = $data->raceDriverEntries();
        $counts = $this->counts($data)->filter(fn (int $count, int $entryId) => $raceDrivers->has($entryId));
        $top = $counts->filter(fn (int $count) => $count === $counts->max() && $count >= $this->minimum());

        return $top->count() !== 1 ? collect() : $top
            ->map(fn (int $count, int $entryId) => new Detection(
                achievementKey: $this->key(),
                driverEntryId: $entryId,
                instanceKey: 'session',
                eventKey: "session:{$entryId}",
                evidence: $this->evidence($data, $entryId),
                detail: $this->detail($count),
            ))
            ->values();
    }

    /**
     * @return array{source: string, rows?: list<array<string, mixed>>, messages?: list<array<string, mixed>>, note?: string}
     */
    protected function evidence(SessionData $data, int $entryId): array
    {
        return Evidence::rows('openf1', $this->counts($data)->sortDesc()->take(5)->map(fn (int $count, int $id) => [
            'driver' => $data->entry($id)?->driver->family_name,
            'count' => $count,
        ])->values()->all());
    }
}
