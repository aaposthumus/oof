<?php

namespace App\Achievements\Rules;

use App\Achievements\Detection;
use App\Achievements\SessionData;
use App\Enums\AwardReviewReason;
use App\Enums\SessionType;
use App\Excavation\Support\RaceStatuses;
use App\Models\Achievement;
use Illuminate\Support\Collection;

/**
 * Race control against Jolpica's final results. Where they disagree, the
 * award is held for review instead of given: a black flag or disqualification
 * the results do not show, or a race penalty for a car without a result.
 */
class CrossCheck
{
    /**
     * Achievements that say the car was disqualified.
     */
    private const DISQUALIFYING = ['expelled'];

    /**
     * @param  Collection<int, Detection>  $detections
     * @param  Collection<string, Achievement>  $catalogue  Keyed by key
     * @return Collection<int, Detection>
     */
    public function apply(Collection $detections, Collection $catalogue, SessionData $data): Collection
    {
        if (! in_array($data->type(), [SessionType::Race, SessionType::Sprint], true) || $data->resultEntries()->isEmpty()) {
            return $detections;
        }

        return $detections->map(function (Detection $detection) use ($catalogue, $data) {
            $disagreement = $this->disagreement($detection, $catalogue[$detection->achievementKey], $data);

            return $disagreement === null ? $detection : $detection->with([
                'reviewReason' => AwardReviewReason::SourceDisagreement,
                'evidence' => [...$detection->evidence, 'note' => $disagreement],
            ]);
        });
    }

    private function disagreement(Detection $detection, Achievement $achievement, SessionData $data): ?string
    {
        if ($detection->driverEntryId === null || ! $achievement->section->assignsFault() && ! in_array($achievement->key, self::DISQUALIFYING, true)) {
            return null;
        }

        $result = $data->entry($detection->driverEntryId)?->result;

        if ($result === null) {
            return 'Race control penalises this car, but Jolpica has no result for it.';
        }

        $disqualifies = in_array($achievement->key, self::DISQUALIFYING, true) || $detection->detail === 'disqualified';

        if ($disqualifies && ! RaceStatuses::isDisqualified($result->status)) {
            return "Race control disqualifies this car, but Jolpica's status is \"{$result->status}\".";
        }

        return null;
    }
}
