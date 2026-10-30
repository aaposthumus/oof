<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Achievements\Support\QualifyingPhases;
use Illuminate\Support\Collection;

/**
 * Knocked out by your own teammate: the first car out of Q1 or Q2, when the
 * car that took the last place through is the teammate.
 */
class FamilyFeudDetector implements Detector
{
    public function key(): string
    {
        return 'family_feud';
    }

    public function detect(SessionData $data): Collection
    {
        $phases = new QualifyingPhases($data);

        return collect([1, 2])
            ->map(function (int $phase) use ($phases) {
                $out = $phases->knockedOut($phase)->first();
                $through = $phases->lastThrough($phase);

                if ($out === null || $through === null || $out->season_team_id !== $through->season_team_id) {
                    return null;
                }

                return new Detection(
                    achievementKey: $this->key(),
                    driverEntryId: $out->id,
                    instanceKey: "q{$phase}",
                    eventKey: "knocked_out:{$out->id}",
                    evidence: Evidence::results([$through, $out], "Q{$phase}: the teammate took the last place through."),
                    detail: "Q{$phase}",
                );
            })
            ->filter()
            ->values();
    }
}
