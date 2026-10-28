<?php

namespace App\Normalizers\OpenF1;

use App\Models\PitLaneVisit;
use App\Models\PitStop;
use App\Normalizers\OpenF1\Support\OpenF1Time;
use App\Normalizers\OpenF1\Support\SessionContext;

/**
 * Every pass through the pit lane, in any session. A race visit is linked to
 * the Jolpica pit stop of the same entry on the same lap (or the next or
 * previous one, which the sources sometimes disagree on); a visit without
 * one is often a drive-through.
 */
class PitNormalizer extends SessionDataNormalizer
{
    public function endpoint(): string
    {
        return 'pit';
    }

    protected function model(): string
    {
        return PitLaneVisit::class;
    }

    protected function uniqueBy(): array
    {
        return ['driver_number', 'entered_at'];
    }

    protected function rows(array $payload, SessionContext $context): array
    {
        $stops = PitStop::query()
            ->whereIn('driver_entry_id', array_filter($context->entryIds))
            ->get(['id', 'driver_entry_id', 'lap'])
            ->groupBy('driver_entry_id');
        $linked = [];
        $rows = [];

        usort($payload, fn (array $a, array $b) => $a['date'] <=> $b['date']);

        foreach ($payload as $visit) {
            $entryId = $context->entryIdFor($visit['driver_number']);
            $stopId = null;

            foreach ([0, 1, -1] as $offset) {
                $stop = $stops->get($entryId)?->first(fn (PitStop $stop) => $stop->lap === ($visit['lap_number'] ?? 0) + $offset && ! isset($linked[$stop->id]));

                if ($stop !== null) {
                    $stopId = $linked[$stop->id] = $stop->id;

                    break;
                }
            }

            $rows[] = [
                'driver_number' => $visit['driver_number'],
                'driver_entry_id' => $entryId,
                'pit_stop_id' => $stopId,
                'lap_number' => $visit['lap_number'] ?? null,
                'entered_at' => OpenF1Time::toDateTime($visit['date']),
                'lane_duration_millis' => OpenF1Time::toMillis($visit['lane_duration'] ?? $visit['pit_duration'] ?? null),
                'stop_duration_millis' => OpenF1Time::toMillis($visit['stop_duration'] ?? null),
            ];
        }

        return $rows;
    }
}
