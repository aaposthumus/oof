<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\QualifyingPhases;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * A car knocked out of Q1 or Q2 with a time in it, measured against the
 * car that took the last place through. Phases without such a car give
 * nothing. It is the car's knock-out event.
 *
 * Attributes: phase, first_out (the fastest car knocked out),
 * through_is_teammate, gap_millis and gap ("0.012") to the last car through.
 */
class QualifyingKnockoutFacts implements FactProvider
{
    public function sources(): array
    {
        return [DataSource::JolpicaQualifying];
    }

    public function availableFrom(): int
    {
        return 2006;
    }

    public function notes(): array
    {
        return [
            'The knockout format starts in 2006.',
            'Sprint qualifying comes from OpenF1 session results.',
        ];
    }

    public function facts(SessionData $data): Collection
    {
        $phases = new QualifyingPhases($data);

        return collect([1, 2])
            ->flatMap(function (int $phase) use ($phases) {
                $through = $phases->lastThrough($phase);
                $column = QualifyingPhases::column($phase);

                if ($through === null) {
                    return [];
                }

                return $phases->knockedOut($phase)->map(function (DriverEntry $out, int $index) use ($phase, $through, $column) {
                    $gap = $out->qualifyingResult->{$column} - $through->qualifyingResult->{$column};

                    return new Fact(
                        type: 'qualifying_knockout',
                        entryId: $out->id,
                        instanceKey: "q{$phase}",
                        eventKey: "knocked_out:{$out->id}",
                        attributes: [
                            'phase' => $phase,
                            'first_out' => $index === 0,
                            'through_is_teammate' => $out->season_team_id === $through->season_team_id,
                            'gap_millis' => $gap,
                            'gap' => sprintf('%.3f', $gap / 1000),
                        ],
                        evidence: FactEvidence::results([$through, $out]),
                    );
                });
            })
            ->values();
    }
}
