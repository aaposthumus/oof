<?php

namespace App\Achievements\Facts;

use App\Achievements\SessionData;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The named predicates a trigger's where list can use.
 */
class Predicates
{
    /**
     * @var array<string, class-string<Predicate>>
     */
    public const PREDICATES = [
        'did_not_start' => Predicates\DidNotStart::class,
        'retired' => Predicates\Retired::class,
        'disqualified' => Predicates\Disqualified::class,
        'not_disqualified' => Predicates\NotDisqualified::class,
        'classified_finisher' => Predicates\ClassifiedFinisher::class,
        'last_classified_finisher' => Predicates\LastClassifiedFinisher::class,
        'is_surprise' => Predicates\IsSurprise::class,
        'took_pole' => Predicates\TookPole::class,
        'led_every_lap' => Predicates\LedEveryLap::class,
        'led_most_laps' => Predicates\LedMostLaps::class,
        'set_fastest_lap' => Predicates\SetFastestLap::class,
        'first_place_outside_the_points' => Predicates\FirstPlaceOutsideThePoints::class,
        'topped_a_practice_session' => Predicates\ToppedAPracticeSession::class,
        'top_team_on_form' => Predicates\TopTeamOnForm::class,
    ];

    /**
     * @var array<string, Predicate>
     */
    private array $predicates = [];

    public function __construct(private Container $container) {}

    public function has(string $name): bool
    {
        return array_key_exists($name, static::PREDICATES);
    }

    public function get(string $name): Predicate
    {
        $class = static::PREDICATES[$name] ?? throw new InvalidArgumentException("No predicate [{$name}].");

        return $this->predicates[$name] ??= $this->container->make($class);
    }

    public function test(string $name, Fact $fact, SessionData $data): bool
    {
        return $this->get($name)->test($fact, $data);
    }
}
