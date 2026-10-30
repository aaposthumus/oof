<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\QualifyingPhases;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * Knocked out of Q1 or Q2 by less than the threshold (0.05 s) to the last car through.
 */
class ByAWhiskerDetector implements Detector
{
    public function key(): string
    {
        return 'by_a_whisker';
    }

    public function detect(SessionData $data): Collection
    {
        $phases = new QualifyingPhases($data);

        return collect([1, 2])
            ->flatMap(function (int $phase) use ($phases) {
                $through = $phases->lastThrough($phase);
                $column = QualifyingPhases::column($phase);

                if ($through === null) {
                    return [];
                }

                $gap = fn (DriverEntry $out) => $out->qualifyingResult->{$column} - $through->qualifyingResult->{$column};

                return $phases->knockedOut($phase)
                    ->filter(fn (DriverEntry $out) => $gap($out) >= 0 && $gap($out) < config('achievements.whisker_millis'))
                    ->map(fn (DriverEntry $out) => new Detection(
                        achievementKey: $this->key(),
                        driverEntryId: $out->id,
                        instanceKey: "q{$phase}",
                        eventKey: "knocked_out:{$out->id}",
                        evidence: Evidence::results([$through, $out], "Q{$phase}: the gap to the last car through."),
                        detail: sprintf('Q%d · +%.3f s', $phase, $gap($out) / 1000),
                    ));
            })
            ->values();
    }
}
