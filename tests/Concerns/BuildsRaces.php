<?php

namespace Tests\Concerns;

use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use App\Models\SeasonTeam;
use Carbon\CarbonImmutable;
use Database\Factories\ResultFactory;

trait BuildsRaces
{
    /**
     * Create a round with a race session holding these results.
     *
     * @param  list<ResultFactory>  $results
     */
    protected function createRace(Season $season, array $results, string $name = 'British Grand Prix'): Round
    {
        $round = Round::factory()->for($season)->create(['name' => $name, 'jolpica_race_name' => $name]);
        $session = RaceSession::factory()->for($round)->create();
        $seasonTeam = SeasonTeam::factory()->for($season)->create();

        foreach ($results as $result) {
            $result->for(DriverEntry::factory()->for($session)->for($seasonTeam))->create();
        }

        return $round;
    }

    /**
     * Create round $roundNumber of a season, dated in round order, with these
     * drivers' results. Entries without a team share one team per race.
     *
     * @param  list<array{0: Driver, 1: ResultFactory, 2?: SeasonTeam}>  $entries
     * @param  array<string, mixed>  $attributes  Round attributes
     */
    protected function createRaceWith(Season $season, int $roundNumber, array $entries, array $attributes = []): Round
    {
        $round = Round::factory()->for($season)->create([
            'round_number' => $roundNumber,
            'date' => CarbonImmutable::create($season->year, 3)->addWeeks($roundNumber)->toDateString(),
            ...$attributes,
        ]);
        $session = RaceSession::factory()->for($round)->create();
        $sharedTeam = null;

        foreach ($entries as $carNumber => $entry) {
            $team = $entry[2] ?? ($sharedTeam ??= SeasonTeam::factory()->for($season)->create());
            $entry[1]->for(DriverEntry::factory()->for($session)->for($entry[0])->for($team)->state(['car_number' => $carNumber + 1]))->create();
        }

        return $round;
    }

    /**
     * Results of a race: finishers in order, then retirements, then non-starters.
     *
     * @return list<ResultFactory>
     */
    protected function field(int $running, int $retired = 0, int $withdrawn = 0): array
    {
        $results = [];

        for ($position = 1; $position <= $running; $position++) {
            $results[] = Result::factory()->classified($position);
        }

        return [
            ...$results,
            ...array_fill(0, $retired, Result::factory()->retired()),
            ...array_fill(0, $withdrawn, Result::factory()->withdrawn()),
        ];
    }
}
