<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\Facts\GatesSessions;
use App\Achievements\SessionData;
use App\Achievements\Support\RunningOrder;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * A one-two lost in the final laps (Dropped the Baton): a team's two cars
 * ran P1 and P2 at the end of at least one of the 5 laps before the leader's
 * last lap, and were not P1 and P2 at the end of that last lap. Judged on
 * the road from the running order (decided by the owner on 21 November
 * 2026), so a disqualification or penalty after the race does not count,
 * and two teammates swapping places still finish one-two. One fact per
 * team, on the car that led the team's last one-two.
 *
 * Attributes: last_lap (the last lap the team ran one-two), laps (the
 * leader's last lap), finish ("P1 and P5", or a car's status).
 */
class LostOneTwoFacts implements FactProvider, GatesSessions
{
    public const FINAL_LAPS = 5;

    public function sources(): array
    {
        return [DataSource::JolpicaResults, DataSource::JolpicaLaps, DataSource::OpenF1Positions, DataSource::OpenF1Laps];
    }

    /**
     * The results decide the session; without a running order there are simply no facts.
     */
    public function sessionSources(): array
    {
        return [DataSource::JolpicaResults];
    }

    public function availableFrom(): int
    {
        return 1996;
    }

    public function notes(): array
    {
        return ['Running order from Jolpica lap times (from 1996); OpenF1 stands in until a race\'s Jolpica laps are in, and the award waits for them.'];
    }

    public function facts(SessionData $data): Collection
    {
        $last = RunningOrder::lastLap($data);

        if ($last === null) {
            return collect();
        }

        $atFlag = self::oneTwoTeam($data, $last);
        $lost = [];

        foreach (range(max(1, $last - self::FINAL_LAPS), $last - 1) as $lap) {
            $team = self::oneTwoTeam($data, $lap);

            if ($team !== null && $team !== $atFlag) {
                $lost[$team] = ['lap' => $lap, 'leader' => RunningOrder::orderAt($data, $lap)[1]];
            }
        }

        return collect($lost)
            ->map(function (array $lostAt, int $seasonTeamId) use ($data, $last) {
                $cars = $data->resultEntries()->where('season_team_id', $seasonTeamId)->values();
                $finish = $cars->map(fn (DriverEntry $car) => ($position = RunningOrder::positionAt($data, $car->id, $last)) !== null ? "P{$position}" : $car->result->status)->implode(' and ');

                return new Fact(
                    type: 'lost_one_two',
                    entryId: $lostAt['leader'],
                    instanceKey: 'one_two',
                    eventKey: "lost_one_two:{$seasonTeamId}",
                    attributes: ['last_lap' => $lostAt['lap'], 'laps' => $last, 'finish' => $finish],
                    evidence: FactEvidence::results($cars, "One-two at the end of lap {$lostAt['lap']} of {$last}; at the flag: {$finish}."),
                );
            })
            ->values();
    }

    /**
     * The season team whose two cars ran P1 and P2 at the end of the lap, if any.
     */
    private static function oneTwoTeam(SessionData $data, int $lap): ?int
    {
        $order = RunningOrder::orderAt($data, $lap);

        if (! isset($order[1], $order[2])) {
            return null;
        }

        $first = $data->entry($order[1])?->season_team_id;

        return $first !== null && $first === $data->entry($order[2])?->season_team_id ? $first : null;
    }
}
