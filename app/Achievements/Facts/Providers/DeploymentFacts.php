<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\Concerns\ReadsRaceControl;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * A safety car or virtual safety car deployment, once per message.
 * Concerns no car: the Safety Car itself.
 *
 * Attributes: kind (safety_car or vsc), lap_label ("lap 12", or null).
 */
class DeploymentFacts implements FactProvider
{
    use ReadsRaceControl;

    public function notes(): array
    {
        return ['A VSC that becomes a safety car has no "VSC ending".'];
    }

    public function facts(SessionData $data): Collection
    {
        return $data->messagesOfType(MessageType::SafetyCarDeployed, MessageType::VirtualSafetyCarDeployed)
            ->map(fn (ClassifiedMessage $message) => new Fact(
                type: 'deployment',
                entryId: null,
                instanceKey: "rcm:{$message->message->id}",
                eventKey: "rcm:{$message->message->id}",
                attributes: [
                    'kind' => $message->is(MessageType::SafetyCarDeployed) ? 'safety_car' : 'vsc',
                    'lap_label' => $message->message->lap_number === null ? null : "lap {$message->message->lap_number}",
                ],
                evidence: FactEvidence::messages([$message]),
            ))
            ->values();
    }
}
