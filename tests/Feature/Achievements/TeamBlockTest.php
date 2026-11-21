<?php

namespace Tests\Feature\Achievements;

use App\Achievements\CatalogueSync;
use App\Achievements\Rules\Sensitivity;
use App\Achievements\WithheldOnSensitiveWeekend;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\DriverEntry;
use App\Models\MomentSensitivity;
use App\Models\RaceSession;
use App\Models\SeasonTeam;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * A driver block also holds back the team awards of that driver's team in
 * the round (the Phase 8 requirement, built in Phase 12b for the first team
 * achievements), proven with a team achievement that exists only in this
 * test. The teammate's own awards stay.
 */
class TeamBlockTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private RaceSession $race;

    private SeasonTeam $red;

    private SeasonTeam $blue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        config(['achievement_catalogue.achievements.team_one_two' => [
            'name' => 'Team One-Two',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => 'Test only.',
            'supersedes' => [],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'team',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'count', 'fact' => 'result', 'where' => [['position', 'in', [1, 2]]], 'distinct' => 'position', 'at_least' => 2, 'instance' => 'one_two'],
        ]]);
        app(CatalogueSync::class)->apply();

        $this->race = $this->raceSession();
        $this->red = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
        $this->blue = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
    }

    /**
     * Red first and second, blue third and fourth, in two races of one round.
     *
     * @return array{0: int, 1: int} The driver IDs of red's winner and runner-up
     */
    private function oneTwo(RaceSession $race): array
    {
        $cars = [$this->car($race, 1, $this->red), $this->car($race, 11, $this->red), $this->car($race, 4, $this->blue), $this->car($race, 81, $this->blue)];

        foreach ($cars as $index => $car) {
            $this->resultFor($car, ['position' => $index + 1, 'position_text' => (string) ($index + 1), 'classification_order' => $index + 1, 'grid' => $index + 1, 'status' => 'Finished']);
        }

        return [$cars[0]->driver_id, $cars[1]->driver_id];
    }

    public function test_a_driver_block_holds_back_the_team_award_and_keeps_the_teammates_own(): void
    {
        [$winner, $runnerUp] = $this->oneTwo($this->race);
        MomentSensitivity::factory()->blocked()->create(['round_id' => $this->race->round_id, 'driver_id' => $runnerUp]);

        $this->runEngine($this->race);

        $this->assertCount(0, $this->awards('team_one_two'));
        $this->assertSame($winner, $this->awards('did_the_thing')->sole()->driver_id);
        $this->assertSame([$this->red->id], Sensitivity::forRound($this->race->round_id)['blocked_season_teams']);
    }

    public function test_a_block_on_another_teams_driver_leaves_the_team_award_flagged(): void
    {
        $this->oneTwo($this->race);
        $blueDriver = DriverEntry::query()->where('race_session_id', $this->race->id)->where('season_team_id', $this->blue->id)->value('driver_id');
        MomentSensitivity::factory()->blocked()->create(['round_id' => $this->race->round_id, 'driver_id' => $blueDriver]);

        $this->runEngine($this->race);

        $award = $this->awards('team_one_two')->sole();
        $this->assertSame([$this->red->id, AwardStatus::Final, true], [$award->season_team_id, $award->status, $award->is_sensitive]);
    }

    public function test_a_block_added_later_suppresses_a_decided_team_award_and_the_guard_refuses_a_new_one(): void
    {
        [, $runnerUp] = $this->oneTwo($this->race);
        $this->runEngine($this->race);
        $award = $this->awards('team_one_two')->sole();
        $award->confirm(User::factory()->create());

        MomentSensitivity::factory()->blocked()->create(['round_id' => $this->race->round_id, 'driver_id' => $runnerUp]);
        $this->runEngine($this->race);

        $this->assertSame([AwardStatus::Suppressed, Sensitivity::SYSTEM_NOTE], [$award->fresh()->status, $award->fresh()->decision_note]);

        $this->expectException(WithheldOnSensitiveWeekend::class);
        $award->fresh()->confirm(User::factory()->create());
    }

    public function test_the_achievement_row_still_records_a_team_recipient(): void
    {
        $this->assertSame('team', Achievement::firstWhere('key', 'team_one_two')->recipient_type->value);
    }
}
