<?php

namespace Tests\Unit\Excavation;

use App\Excavation\Support\EvidenceSummary;
use App\Models\Driver;
use App\Models\MomentCandidate;
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
