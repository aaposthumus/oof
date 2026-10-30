<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Enums\SessionType;
use App\Models\RaceControlMessage;
use App\Models\Round;
use App\RaceControl\MessageClassifier;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * Safety Car corner: the season's count of deployments in races and sprints,
 * for the end-of-season awards. Awarded at the final race once it has results.
 */
class FrequentFlyerDetector implements Detector
{
    public function __construct(private MessageClassifier $classifier) {}

    public function key(): string
    {
        return 'frequent_flyer';
    }

    public function detect(SessionData $data): Collection
    {
        $round = $data->session->round;
        $finalRound = Round::query()->where('season_id', $round->season_id)->max('round_number');

        if ($round->round_number !== $finalRound || $data->resultEntries()->isEmpty()) {
            return collect();
        }

        $deployments = RaceControlMessage::query()
            ->where('message', 'like', '%SAFETY CAR DEPLOYED%')
            ->whereHas('raceSession', fn ($query) => $query
                ->whereIn('type', [SessionType::Race, SessionType::Sprint])
                ->whereHas('round', fn ($query) => $query->where('season_id', $round->season_id)))
            ->with('raceSession.round')
            ->get()
            ->filter(fn (RaceControlMessage $message) => $this->classifier->classify($message->message, $message->flag)?->type === MessageType::SafetyCarDeployed);

        return collect([new Detection(
            achievementKey: $this->key(),
            driverEntryId: null,
            instanceKey: 'season',
            eventKey: 'season',
            evidence: [
                'source' => 'race_control',
                'rows' => $deployments->countBy(fn (RaceControlMessage $message) => "Round {$message->raceSession->round->round_number} {$message->raceSession->type->label()}")
                    ->map(fn (int $count, string $session) => ['session' => $session, 'deployments' => $count])
                    ->values()
                    ->all(),
            ],
            detail: "{$deployments->count()} deployments",
        )]);
    }
}
