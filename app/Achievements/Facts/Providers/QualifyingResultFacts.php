<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\DescribesAttributes;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\PracticeToppers;
use App\Achievements\Support\QualifyingPhases;
use App\Achievements\Support\Surprise;
use App\Achievements\Support\TeamForm;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * One car's qualifying or sprint qualifying classification.
 *
 * Attributes: position, q1_millis, q1_time, knocked_out_in (1 or 2, null
 * for a car that reached the last phase; see QualifyingPhases),
 * team_form_rank (the lasting team's rank on form before the round, null
 * without a race in the window; see TeamForm), topped_practice (the practice
 * sessions of the round the driver topped, "FP1 and FP3", or null; see
 * PracticeToppers), surprise (detail and note when the team's result is a
 * surprise on form, else null; see Surprise).
 */
class QualifyingResultFacts implements DescribesAttributes, FactProvider
{
    public function sources(): array
    {
        return [DataSource::JolpicaQualifying];
    }

    public function availableFrom(): int
    {
        return 1994;
    }

    public function notes(): array
    {
        return ['Sprint qualifying comes from OpenF1 session results.'];
    }

    public function attributeYears(): array
    {
        return ['knocked_out_in' => 2006];
    }

    public function attributeNotes(): array
    {
        return ['knocked_out_in' => 'The knockout format starts in 2006.'];
    }

    public function facts(SessionData $data): Collection
    {
        $phases = new QualifyingPhases($data);
        $form = null;
        $formRank = function (DriverEntry $entry) use ($data, &$form): ?int {
            $form ??= TeamForm::before($data->session->round);

            return $entry->seasonTeam === null ? null : $form->rankOf($entry->seasonTeam->team_id);
        };

        return $data->entries
            ->filter(fn (DriverEntry $entry) => $entry->qualifyingResult !== null)
            ->map(fn (DriverEntry $entry) => new Fact(
                type: 'qualifying_result',
                entryId: $entry->id,
                instanceKey: 'qualifying',
                eventKey: "qualifying:{$entry->id}",
                attributes: [
                    'position' => $entry->qualifyingResult->position,
                    'q1_millis' => $entry->qualifyingResult->q1_millis,
                    'q1_time' => $entry->qualifyingResult->q1_millis === null ? null : QualifyingPhases::seconds($entry->qualifyingResult->q1_millis),
                    'knocked_out_in' => match (true) {
                        ! $phases->reached($entry, 2) => 1,
                        ! $phases->reached($entry, 3) => 2,
                        default => null,
                    },
                    'team_form_rank' => fn () => $formRank($entry),
                    'topped_practice' => function () use ($entry, $data): ?string {
                        $sessions = PracticeToppers::sessionsToppedBy($entry->driver_id, $data);

                        return $sessions === [] ? null : implode(' and ', $sessions);
                    },
                    'surprise' => fn () => Surprise::of($entry, $data),
                ],
                evidence: FactEvidence::results([$entry]),
            ))
            ->values();
    }
}
