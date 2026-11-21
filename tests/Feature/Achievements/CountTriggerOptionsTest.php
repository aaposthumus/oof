<?php

namespace Tests\Feature\Achievements;

use App\Achievements\CatalogueSync;
use App\Enums\SessionType;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\SeasonTeam;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The count trigger's options added in Phase 12b, proven with achievements
 * that exist only in this test: per driver (a total over a weekend's
 * sessions, as Encore needs) and distinct (as In Tandem needs).
 */
class CountTriggerOptionsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addAchievement(string $key, array $overrides): void
    {
        config(["achievement_catalogue.achievements.{$key}" => [
            'name' => str($key)->headline()->toString(),
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Test only.',
            'supersedes' => [],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            ...$overrides,
        ]]);

        app(CatalogueSync::class)->apply();
    }

    private function finish(DriverEntry $entry, int $position): void
    {
        $this->resultFor($entry, ['position' => $position, 'position_text' => (string) $position, 'classification_order' => $position, 'grid' => $position, 'status' => 'Finished']);
    }

    public function test_per_driver_counts_a_drivers_facts_across_the_weekends_sessions(): void
    {
        $this->addAchievement('double_winner', [
            'sessions' => ['sprint', 'race'],
            'scope' => 'weekend',
            'decided_after' => 'race',
            'trigger' => ['type' => 'count', 'fact' => 'result', 'where' => [['position', '=', 1], 'not_disqualified'], 'per' => 'driver', 'at_least' => 2, 'instance' => 'double', 'detail' => '{total} wins'],
        ]);
        $round = $this->round();
        $sprint = $this->raceSession(SessionType::Sprint, $round);
        $race = $this->raceSession(SessionType::Race, $round);
        $alice = Driver::factory()->create();
        $bob = Driver::factory()->create();
        $this->finish($this->car($sprint, 1, null, $alice), 1);
        $this->finish($this->car($sprint, 2, null, $bob), 2);
        $aliceInTheRace = $this->car($race, 1, null, $alice);
        $this->finish($aliceInTheRace, 1);
        $this->finish($this->car($race, 2, null, $bob), 2);

        $detection = $this->detect('double_winner', $race)->sole();

        $this->assertSame($aliceInTheRace->id, $detection->recipient->entryId);
        $this->assertSame('2 wins', $detection->detail);
        $this->assertSame("e{$aliceInTheRace->id}:double", $detection->awardInstanceKey());
    }

    public function test_per_recipient_still_counts_per_car_so_one_car_per_session_never_reaches_two(): void
    {
        $this->addAchievement('double_winner_per_car', [
            'sessions' => ['sprint', 'race'],
            'scope' => 'weekend',
            'decided_after' => 'race',
            'trigger' => ['type' => 'count', 'fact' => 'result', 'where' => [['position', '=', 1]], 'at_least' => 2],
        ]);
        $round = $this->round();
        $sprint = $this->raceSession(SessionType::Sprint, $round);
        $race = $this->raceSession(SessionType::Race, $round);
        $alice = Driver::factory()->create();
        $this->finish($this->car($sprint, 1, null, $alice), 1);
        $this->finish($this->car($race, 1, null, $alice), 1);

        $this->assertCount(0, $this->detect('double_winner_per_car', $race));
    }

    public function test_distinct_counts_different_values_so_a_shared_car_is_one_place(): void
    {
        $this->addAchievement('one_two', [
            'recipient' => 'team',
            'trigger' => ['type' => 'count', 'fact' => 'result', 'where' => [['position', 'in', [1, 2]], 'not_disqualified'], 'distinct' => 'position', 'at_least' => 2, 'instance' => 'one_two', 'detail' => '{total} places'],
        ]);
        $race = $this->raceSession(SessionType::Race, $this->round(1951));
        $red = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $blue = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        // A shared car: both of red's drivers are credited with the win.
        $this->finish($this->car($race, 8, $red), 1);
        $this->finish($this->car($race, 8, $red), 1);
        $this->finish($this->car($race, 4, $blue), 2);

        $this->assertCount(0, $this->detect('one_two', $race));

        $this->finish($this->car($race, 9, $blue), 3);
        $other = $this->raceSession(SessionType::Race, $this->round(1951, 6));
        $green = SeasonTeam::factory()->create(['season_id' => $other->round->season_id]);
        $this->finish($this->car($other, 1, $green), 1);
        $this->finish($this->car($other, 2, $green), 2);

        $detection = $this->detect('one_two', $other)->sole();
        $this->assertSame($green->id, $detection->recipient->seasonTeamId);
        $this->assertSame('2 places', $detection->detail);
    }
}
