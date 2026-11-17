<?php

namespace App\Achievements\Facts;

use App\Achievements\SessionData;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use WeakMap;

/**
 * The fact providers by fact type. Facts are built once per session and
 * shared by every trigger that reads them.
 */
class Facts
{
    /**
     * @var array<string, class-string<FactProvider>>
     */
    public const PROVIDERS = [
        'penalised_offence' => Providers\PenalisedOffenceFacts::class,
        'penalty' => Providers\PenaltyFacts::class,
        'offence_noted' => Providers\OffenceNotedFacts::class,
        'incident_involvement' => Providers\IncidentInvolvementFacts::class,
        'flag' => Providers\FlagFacts::class,
        'track_limit_deletion' => Providers\TrackLimitDeletionFacts::class,
        'result' => Providers\ResultFacts::class,
        'qualifying_result' => Providers\QualifyingResultFacts::class,
        'pit_stop' => Providers\PitStopFacts::class,
        'qualifying_knockout' => Providers\QualifyingKnockoutFacts::class,
        'lap_count' => Providers\LapCountFacts::class,
        'safety_car_period' => Providers\SafetyCarPeriodFacts::class,
        'deployment' => Providers\DeploymentFacts::class,
        'cause' => Providers\CauseFacts::class,
    ];

    /**
     * @var WeakMap<SessionData, array<string, Collection<int, Fact>>>
     */
    private WeakMap $built;

    /**
     * @var array<string, FactProvider>
     */
    private array $providers = [];

    public function __construct(private Container $container)
    {
        $this->built = new WeakMap;
    }

    public function provider(string $type): FactProvider
    {
        $class = self::PROVIDERS[$type] ?? throw new InvalidArgumentException("No fact provider for [{$type}].");

        return $this->providers[$type] ??= $this->container->make($class);
    }

    /**
     * @return Collection<int, Fact>
     */
    public function of(SessionData $data, string $type): Collection
    {
        $built = $this->built[$data] ?? [];

        if (! isset($built[$type])) {
            $built[$type] = $this->provider($type)->facts($data)->values();
            $this->built[$data] = $built;
        }

        return $built[$type];
    }
}
