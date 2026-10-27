<?php

namespace Tests\Unit\Excavation;

use App\Excavation\ExcavationRunner;
use App\Excavation\Support\EvidenceSummary;
use App\Models\Driver;
use App\Models\MomentCandidate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EvidenceSummaryTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    private function candidate(string $type, array $evidence, ?Driver $driver = null): MomentCandidate
    {
        return (new MomentCandidate(['type' => $type, 'evidence' => $evidence]))->setRelation('driver', $driver);
    }

    public function test_summarises_fewest_starters(): void
    {
        $candidate = $this->candidate('fewest_starters', ['starters' => 6, 'entries' => 20]);

        $this->assertSame('6 of 20 started', EvidenceSummary::summary($candidate));
        $this->assertSame('only 6 cars started', EvidenceSummary::titlePhrase($candidate));
    }

    public function test_summarises_fewest_finishers(): void
    {
        $candidate = $this->candidate('fewest_finishers', ['running' => 4, 'starters' => 18]);

        $this->assertSame('4 of 18 running at the flag', EvidenceSummary::summary($candidate));
    }

    public function test_summarises_a_grid_climb_with_the_winner_in_the_title(): void
    {
        $driver = new Driver(['given_name' => 'John', 'family_name' => 'Watson']);
        $candidate = $this->candidate('grid_to_win_climb', ['grid' => 22, 'pit_lane_start' => false, 'places_gained' => 21], $driver);

        $this->assertSame('Won from 22, 21 places gained', EvidenceSummary::summary($candidate));
        $this->assertSame('John Watson won from 22nd on the grid', EvidenceSummary::titlePhrase($candidate));
    }

    /**
     * Evidence as stored (after JSON), with the summary and title phrase it gives for driver Nico Hülkenberg.
     *
     * @return array<string, array{string, array<string, mixed>, string, string}>
     */
    public static function newTypes(): array
    {
        return [
            'surprise winner' => ['surprise_winner', ['team_position_before' => 9, 'teams_before' => 11, 'only_career_win' => true], "Won with a team 9th of 11 in the constructors' standings, only career win", "Nico Hülkenberg won with a team 9th of 11 in the constructors' standings"],
            'surprise only win' => ['surprise_winner', ['team_position_before' => null, 'teams_before' => null, 'only_career_win' => true], 'Only career win', 'Nico Hülkenberg took the only win of their career'],
            'first-time winner' => ['first_time_winner', ['start_number' => 16, 'career_wins' => 1, 'one_hit_wonder' => true], 'First win, at start 16 (only career win)', 'Nico Hülkenberg won for the first time and never again'],
            'win drought' => ['win_drought_ended', ['starts_before' => 189], 'First win after 189 starts', 'Nico Hülkenberg won for the first time after 189 starts'],
            'podium drought' => ['podium_drought_ended', ['starts_before' => 238], 'First podium after 238 starts', 'Nico Hülkenberg reached the podium for the first time after 238 starts'],
            'most retirements' => ['most_retirements', ['retirements' => 21, 'starters' => 26, 'top_statuses' => 'Spun off ×6'], '21 of 26 starters retired (Spun off ×6)', '21 of 26 starters retired'],
            'curious retirement' => ['curious_retirement', ['status' => 'Out of fuel', 'laps_completed' => 50], 'Out of fuel, after 50 laps', 'Nico Hülkenberg\'s race ended with "out of fuel"'],
            'disqualified winner' => ['disqualification', ['disqualified' => 1, 'drivers' => 'Nico Hülkenberg', 'road_position' => 1, 'full_distance' => true], '1 disqualified: Nico Hülkenberg (first named finished 1st on the road)', 'Nico Hülkenberg was disqualified after finishing first on the road'],
            'disqualified pair' => ['disqualification', ['disqualified' => 2, 'drivers' => 'Nico Hülkenberg, Daniel Ricciardo', 'road_position' => null, 'full_distance' => true], '2 disqualified: Nico Hülkenberg, Daniel Ricciardo (first named ran the full distance)', '2 drivers were disqualified'],
            'shortest race with a configured distance' => ['shortest_race', ['laps' => 20, 'estimated_scheduled_laps' => 50.0, 'scheduled_laps_override' => true, 'half_points' => false], '20 of 50 laps', 'the race ended after 20 laps'],
            'shortest race' => ['shortest_race', ['laps' => 1, 'estimated_scheduled_laps' => 44, 'half_points' => true], '1 of about 44 laps, half points', 'the race ended after 1 lap, with half points'],
            'dominant by laps' => ['dominant_win', ['laps_ahead' => 2, 'runner_up' => 'Bruce McLaren', 'gap_ms' => null, 'lapped_rivals' => 13, 'classified_rivals' => 13], 'Won by 2 laps from Bruce McLaren', 'Nico Hülkenberg lapped the whole field'],
            'dominant by gap' => ['dominant_win', ['laps_ahead' => 0, 'runner_up' => 'Bruce McLaren', 'gap_ms' => 150000, 'lapped_rivals' => 2, 'classified_rivals' => 10], 'Won by 150.0 s, 2 of 10 classified rivals lapped', 'Nico Hülkenberg won by 150.0 seconds'],
            'closest finish' => ['closest_finish', ['gap_ms' => 10, 'runner_up' => 'Ronnie Peterson'], 'Won by 0.010 s from Ronnie Peterson', 'Nico Hülkenberg beat Ronnie Peterson by 0.010 seconds'],
            'title lead change' => ['title_decider', ['champion' => 'Kimi Räikkönen', 'runner_up' => 'Lewis Hamilton', 'final_margin' => 1, 'leader_before_final' => 'Lewis Hamilton', 'lead_change' => true], 'Kimi Räikkönen champion by 1 point over Lewis Hamilton, Lewis Hamilton led before the final race', 'Kimi Räikkönen took the title from Lewis Hamilton in the final race'],
            'title close' => ['title_decider', ['champion' => 'Niki Lauda', 'runner_up' => 'Alain Prost', 'final_margin' => 0.5, 'leader_before_final' => 'Niki Lauda', 'lead_change' => false], 'Niki Lauda champion by 0.5 points over Alain Prost', 'Niki Lauda won the title by 0.5 points'],
            'age record' => ['age_record', ['records' => 'youngest winner #1, youngest podium finisher #1', 'age_years' => 18, 'best_record' => 'youngest winner', 'best_rank' => 1], 'Youngest winner #1, youngest podium finisher #1, aged 18', 'Nico Hülkenberg became the youngest winner ever, aged 18'],
            'age runner-up' => ['age_record', ['records' => 'oldest pole sitter #2', 'age_years' => 46, 'best_record' => 'oldest pole sitter', 'best_rank' => 2], 'Oldest pole sitter #2, aged 46', 'Nico Hülkenberg became the 2nd-oldest pole sitter, aged 46'],
            'start milestone' => ['start_milestone', ['start_number' => 300, 'first_to_reach' => true], '300th start (first driver to reach it)', 'Nico Hülkenberg made their 300th start, the first driver to get there'],
            'slowest pit stop' => ['slowest_pit_stop', ['duration_ms' => 162042, 'ratio' => 8.16, 'lap' => 8], 'Stop of 162.0 s (8.2× a typical stop) on lap 8', 'Nico Hülkenberg spent 162.0 seconds in the pit lane'],
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    #[DataProvider('newTypes')]
    public function test_summarises_the_new_types(string $type, array $evidence, string $summary, string $titlePhrase): void
    {
        $candidate = $this->candidate($type, $evidence, new Driver(['given_name' => 'Nico', 'family_name' => 'Hülkenberg']));

        $this->assertSame($summary, EvidenceSummary::summary($candidate));
        $this->assertSame($titlePhrase, EvidenceSummary::titlePhrase($candidate));
    }

    public function test_every_excavator_has_a_type_label(): void
    {
        $keys = array_keys(app(ExcavationRunner::class)->excavators());

        $this->assertSame($keys, array_keys(array_intersect_key(EvidenceSummary::typeLabels(), array_flip($keys))));
    }

    public function test_an_unknown_type_falls_back_to_a_readable_label(): void
    {
        $this->assertSame('Most Red Flags', EvidenceSummary::typeLabel('most_red_flags'));
        $this->assertSame('Red Flags: 3', EvidenceSummary::summary($this->candidate('most_red_flags', ['red_flags' => 3, 'raw_score' => 9.0])));
    }

    public function test_details_are_readable(): void
    {
        $details = EvidenceSummary::details($this->candidate('grid_to_win_climb', ['pit_lane_start' => true, 'ratio' => 0.5, 'raw_score' => 80.0]));

        $this->assertSame(['Pit Lane Start' => 'Yes', 'Ratio' => '0.5', 'Raw score (before ranking)' => '80'], $details);
    }
}
