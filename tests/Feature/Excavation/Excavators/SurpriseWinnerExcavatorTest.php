<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\SurpriseWinnerExcavator;
use App\Models\Driver;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\BuildsRaces;
use Tests\TestCase;

class SurpriseWinnerExcavatorTest extends TestCase
{
    use BuildsRaces, LazilyRefreshDatabase;

    /** @var Collection<int, Driver> */
    private Collection $drivers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.surprise_winner.only_win_bonus' => 40,
            'achievements.surprise.window_races' => 10,
            'achievements.surprise.top_teams' => 4,
        ]);
        $this->drivers = Driver::factory()->count(6)->create();
    }

    /**
     * @return Collection<int, SeasonTeam>
     */
    private function teams(Season $season, int $count = 6): Collection
    {
        return SeasonTeam::factory()->for($season)->count($count)->create();
    }

    /**
     * A race in which driver N drives for team N and scores 6 - N points, unless another winner is named.
     *
     * @param  Collection<int, SeasonTeam>  $teams
     */
    private function race(Season $season, int $roundNumber, Collection $teams, int $winner, array $attributes = []): Round
    {
        $position = 2;

        return $this->createRaceWith($season, $roundNumber, $this->drivers->take($teams->count())->map(fn (Driver $driver, int $index) => [
            $driver,
            Result::factory()->classified($index === $winner ? 1 : $position++)->state(['points' => $teams->count() - $index - 1]),
            $teams[$index],
        ])->all(), $attributes);
    }

    public function test_hits_a_win_by_a_team_last_on_form_and_an_only_career_win(): void
    {
        $season = Season::factory()->create(['year' => 2012]);
        $teams = $this->teams($season);
        $first = $this->race($season, 1, $teams, winner: 0);
        $surprise = $this->race($season, 2, $teams, winner: 5);
        $again = $this->race($season, 3, $teams, winner: 5);

        $candidates = app(SurpriseWinnerExcavator::class)->dig(new DigParameters)->keyBy('roundId');

        $this->assertSame([$first->id, $surprise->id, $again->id], $candidates->keys()->all());
        $this->assertSame($this->drivers[5]->id, $candidates[$surprise->id]->driverId);
        $this->assertSame(100.0, $candidates[$surprise->id]->score);
        $this->assertSame([
            'team_form_rank' => 6,
            'teams_in_window' => 6,
            'window_races' => 1,
            'team_points_per_race' => 0.0,
            'team_relative' => 1.0,
            'career_wins' => 2,
            'only_career_win' => false,
        ], $candidates[$surprise->id]->evidence);

        // The very first race has an empty window: only the only career win counts.
        $this->assertSame(40.0, $candidates[$first->id]->score);
        $this->assertNull($candidates[$first->id]->evidence['team_form_rank']);
        $this->assertNull($candidates[$first->id]->evidence['window_races']);
    }

    public function test_a_win_by_a_top_four_team_on_form_is_no_hit_and_the_score_follows_the_rank(): void
    {
        $season = Season::factory()->create(['year' => 2012]);
        $teams = $this->teams($season);
        $this->race($season, 1, $teams, winner: 0);
        $this->race($season, 2, $teams, winner: 3);
        $this->race($season, 3, $teams, winner: 3);
        $fifth = $this->race($season, 4, $teams, winner: 4);
        $this->race($season, 5, $teams, winner: 4);

        $candidates = app(SurpriseWinnerExcavator::class)->dig(new DigParameters)->keyBy('roundId');

        $this->assertSame(80.0, $candidates[$fifth->id]->score);
        $this->assertSame(5, $candidates[$fifth->id]->evidence['team_form_rank']);
        $this->assertNull($candidates->firstWhere('driverId', $this->drivers[3]->id));
    }

    public function test_the_window_reaches_into_the_previous_season_through_the_lasting_team(): void
    {
        $lasting = Team::factory()->count(6)->create();
        $previous = Season::factory()->create(['year' => 2011]);
        $this->race($previous, 1, $lasting->map(fn (Team $team) => SeasonTeam::factory()->for($previous)->for($team)->create()), winner: 0);
        $this->race($previous, 2, $lasting->map(fn (Team $team) => SeasonTeam::factory()->for($previous)->for($team)->create(['jolpica_constructor_id' => $team->slug.'_old'])), winner: 0);
        $season = Season::factory()->create(['year' => 2012]);
        $round = $this->race($season, 1, $lasting->map(fn (Team $team) => SeasonTeam::factory()->for($season)->for($team)->create()), winner: 5);
        $this->race($season, 2, $lasting->map(fn (Team $team) => SeasonTeam::factory()->for($season)->for($team)->create(['jolpica_constructor_id' => $team->slug.'_new'])), winner: 5);

        $candidate = app(SurpriseWinnerExcavator::class)->dig(new DigParameters(seasons: [2012]))->firstWhere('roundId', $round->id);

        $this->assertSame(6, $candidate->evidence['team_form_rank']);
        $this->assertSame(2, $candidate->evidence['window_races']);
    }

    public function test_a_win_by_a_team_without_form_is_no_surprise_but_an_only_career_win_still_counts(): void
    {
        $season = Season::factory()->create(['year' => 1954]);
        $this->race($season, 1, $this->teams($season), winner: 0);
        $debut = $this->race($season, 2, $this->teams($season), winner: 0);
        $onlyWin = $this->race($season, 3, $this->teams($season), winner: 5);

        $candidates = app(SurpriseWinnerExcavator::class)->dig(new DigParameters)->keyBy('roundId');

        $this->assertFalse($candidates->has($debut->id));
        $this->assertSame(40.0, $candidates[$onlyWin->id]->score);
        $this->assertNull($candidates[$onlyWin->id]->evidence['team_form_rank']);
        $this->assertNull($candidates[$onlyWin->id]->evidence['window_races']);
    }

    public function test_an_indianapolis_500_win_is_never_a_team_surprise(): void
    {
        $season = Season::factory()->create(['year' => 1955]);
        $teams = $this->teams($season);
        $this->race($season, 1, $teams, winner: 0);
        $this->race($season, 2, $teams, winner: 0);
        $this->race($season, 3, $this->teams($season), winner: 5, attributes: ['name' => 'Indianapolis 500', 'jolpica_race_name' => 'Indianapolis 500']);
        $this->race($season, 4, $this->teams($season), winner: 5, attributes: ['name' => 'Indianapolis 500', 'jolpica_race_name' => 'Indianapolis 500']);

        $this->assertSame([], app(SurpriseWinnerExcavator::class)->dig(new DigParameters)->where('driverId', $this->drivers[5]->id)->all());
    }
}
