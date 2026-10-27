<?php

namespace Tests\Feature\Excavation\Excavators;

use App\Excavation\DigParameters;
use App\Excavation\Excavators\SurpriseWinnerExcavator;
use App\Models\ConstructorStanding;
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

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'excavators.surprise_winner.min_position' => 5,
            'excavators.surprise_winner.min_relative' => 0.4,
            'excavators.surprise_winner.only_win_bonus' => 40,
            'excavators.surprise_winner.early_season_rounds' => 1,
            'excavators.surprise_winner.excluded_teams' => [],
        ]);
    }

    public function test_hits_a_win_by_a_team_last_in_the_standings_and_an_only_career_win(): void
    {
        $season = Season::factory()->create(['year' => 2012]);
        $teams = SeasonTeam::factory()->for($season)->count(6)->create();
        $drivers = Driver::factory()->count(6)->create();
        $first = $this->race($season, 1, $drivers, $teams, winner: 0);
        $this->standings($first, $teams);
        $surprise = $this->race($season, 2, $drivers, $teams, winner: 5);
        $this->race($season, 3, $drivers, $teams, winner: 5);

        $candidates = app(SurpriseWinnerExcavator::class)->dig(new DigParameters)->keyBy('roundId');

        $this->assertSame([$first->id, $surprise->id], $candidates->keys()->all());
        $this->assertSame($drivers[5]->id, $candidates[$surprise->id]->driverId);
        $this->assertSame(100.0, $candidates[$surprise->id]->score);
        $this->assertSame([
            'team_position_before' => 6,
            'teams_before' => 6,
            'standings_from_previous_season' => false,
            'team_relative' => 1.0,
            'career_wins' => 2,
            'only_career_win' => false,
        ], $candidates[$surprise->id]->evidence);
        $this->assertSame(40.0, $candidates[$first->id]->score);
        $this->assertTrue($candidates[$first->id]->evidence['only_career_win']);
        $this->assertNull($candidates[$first->id]->evidence['team_position_before']);
    }

    public function test_the_first_round_uses_the_final_standings_of_the_season_before(): void
    {
        $teams = Team::factory()->count(5)->create();
        $previous = Season::factory()->create(['year' => 2011]);
        $previousTeams = $teams->map(fn (Team $team) => SeasonTeam::factory()->for($previous)->for($team)->create());
        $drivers = Driver::factory()->count(5)->create();
        $this->standings($this->race($previous, 1, $drivers, $previousTeams, winner: 0), $previousTeams);
        $season = Season::factory()->create(['year' => 2012]);
        $seasonTeams = $teams->map(fn (Team $team) => SeasonTeam::factory()->for($season)->for($team)->create());
        $this->race($season, 1, $drivers, $seasonTeams, winner: 4);
        $this->race($season, 2, $drivers, $seasonTeams, winner: 4);

        $candidate = app(SurpriseWinnerExcavator::class)->dig(new DigParameters(seasons: [2012]))->sole();

        $this->assertSame(5, $candidate->evidence['team_position_before']);
        $this->assertTrue($candidate->evidence['standings_from_previous_season']);
    }

    public function test_a_team_without_points_stands_below_the_scoring_teams(): void
    {
        config(['excavators.surprise_winner.min_position' => 3]);
        $season = Season::factory()->create(['year' => 2012]);
        $teams = SeasonTeam::factory()->for($season)->count(5)->create();
        $drivers = Driver::factory()->count(5)->create();
        $this->standings($this->race($season, 1, $drivers, $teams, winner: 0), $teams->take(2));
        $this->race($season, 2, $drivers, $teams, winner: 4);
        $this->race($season, 3, $drivers, $teams, winner: 4);

        $candidates = app(SurpriseWinnerExcavator::class)->dig(new DigParameters);

        $this->assertSame([3, 5], [$candidates->last()->evidence['team_position_before'], $candidates->last()->evidence['teams_before']]);
    }

    public function test_the_early_season_rounds_use_the_final_standings_of_the_season_before(): void
    {
        config(['excavators.surprise_winner.early_season_rounds' => 3]);
        [$teams, $drivers] = $this->twoSeasons();

        $candidates = app(SurpriseWinnerExcavator::class)->dig(new DigParameters(seasons: [2012]))->keyBy('roundId');

        $this->assertCount(3, $candidates);
        $this->assertTrue($candidates->every(fn ($candidate) => $candidate->evidence['standings_from_previous_season'] && $candidate->evidence['team_position_before'] === 5));

        config(['excavators.surprise_winner.early_season_rounds' => 2]);

        $this->assertCount(2, app(SurpriseWinnerExcavator::class)->dig(new DigParameters(seasons: [2012])));
    }

    public function test_a_team_on_the_exclusion_list_has_an_unknown_position(): void
    {
        config(['excavators.surprise_winner.early_season_rounds' => 3, 'excavators.surprise_winner.excluded_teams' => [['year' => 2011, 'team' => 'team-5']]]);
        $this->twoSeasons();

        $this->assertCount(0, app(SurpriseWinnerExcavator::class)->dig(new DigParameters(seasons: [2012])));
    }

    public function test_a_team_missing_from_the_standings_although_its_drivers_scored_has_an_unknown_position(): void
    {
        $season = Season::factory()->create(['year' => 2012]);
        $teams = SeasonTeam::factory()->for($season)->count(6)->create();
        $drivers = Driver::factory()->count(6)->create();
        $first = $this->race($season, 1, $drivers, $teams, winner: 0, scorer: 5);
        $this->standings($first, $teams->take(5));
        $this->race($season, 2, $drivers, $teams, winner: 5);
        $this->race($season, 3, $drivers, $teams, winner: 5);

        $candidates = app(SurpriseWinnerExcavator::class)->dig(new DigParameters);

        $this->assertNull($candidates->firstWhere('driverId', $drivers[5]->id));
    }

    /**
     * Season 2011 with five teams in standings order and 2012 with the same
     * teams, in which team 5 (last the season before) wins three rounds.
     *
     * @return array{Collection<int, Team>, Collection<int, Driver>}
     */
    private function twoSeasons(): array
    {
        $teams = collect(range(1, 5))->map(fn (int $number) => Team::factory()->create(['slug' => "team-{$number}"]));
        $previous = Season::factory()->create(['year' => 2011]);
        $previousTeams = $teams->map(fn (Team $team) => SeasonTeam::factory()->for($previous)->for($team)->create());
        $drivers = Driver::factory()->count(5)->create();
        $this->standings($this->race($previous, 1, $drivers, $previousTeams, winner: 0), $previousTeams);
        $season = Season::factory()->create(['year' => 2012]);
        $seasonTeams = $teams->map(fn (Team $team) => SeasonTeam::factory()->for($season)->for($team)->create());

        foreach ([1, 2, 3] as $round) {
            $this->race($season, $round, $drivers, $seasonTeams, winner: 4);
        }

        return [$teams, $drivers];
    }

    /**
     * @param  Collection<int, Driver>  $drivers
     * @param  Collection<int, SeasonTeam>  $teams
     */
    private function race(Season $season, int $roundNumber, Collection $drivers, Collection $teams, int $winner, ?int $scorer = null): Round
    {
        $position = 2;

        return $this->createRaceWith($season, $roundNumber, $drivers->map(fn (Driver $driver, int $index) => [
            $driver,
            Result::factory()->classified($index === $winner ? 1 : $position++)->state(['points' => $index === $scorer ? 1 : 0]),
            $teams[$index],
        ])->all());
    }

    /**
     * @param  Collection<int, SeasonTeam>  $teams  In standings order
     */
    private function standings(Round $round, Collection $teams): void
    {
        $teams->values()->each(fn (SeasonTeam $team, int $index) => ConstructorStanding::create([
            'round_id' => $round->id,
            'season_team_id' => $team->id,
            'position' => $index + 1,
            'position_text' => (string) ($index + 1),
            'points' => 10 - $index,
            'wins' => 0,
        ]));
    }
}
