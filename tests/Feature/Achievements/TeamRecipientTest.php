<?php

namespace Tests\Feature\Achievements;

use App\Achievements\CatalogueSync;
use App\Achievements\OofScoreStandings;
use App\Achievements\Triggers\TriggerFactory;
use App\Enums\AchievementShelf;
use App\Enums\AwardStatus;
use App\Enums\RecipientType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use App\Models\OofscoreStanding;
use App\Models\RaceSession;
use App\Models\SeasonTeam;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * Team recipients, proven with a team achievement that exists only in this
 * test: no team achievement ships (decision 3 of the plan).
 */
class TeamRecipientTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    public function test_a_team_achievement_from_the_catalogue_alone_is_awarded_to_the_team_end_to_end(): void
    {
        $this->addDoublePodium();
        [$race, $red, $blue, $redCars] = $this->podiumRace();

        $stats = $this->runEngine($race);
        $award = $this->awards('double_podium')->sole();

        $this->assertSame(RecipientType::Team, $award->recipient_type);
        $this->assertSame($red->id, $award->season_team_id);
        $this->assertSame($red->team_id, $award->team_id);
        $this->assertNull($award->driver_entry_id);
        $this->assertNull($award->driver_id);
        $this->assertSame("t{$red->id}:double_podium", $award->instance_key);
        $this->assertSame('2 cars on the podium', $award->detail);
        $this->assertSame(AwardStatus::Final, $award->status);
        $this->assertSame($red->name, $award->recipientLabel());
        $this->assertSame(['found' => 1, 'created' => 1, 'updated' => 0, 'removed' => 0, 'pending' => 0], $stats['double_podium']);

        // Re-running finds the same award and stores nothing new.
        $this->assertSame(['found' => 1, 'created' => 0, 'updated' => 0, 'removed' => 0, 'pending' => 0], $this->runEngine($race)['double_podium']);
        $this->assertSame(1, AchievementAward::query()->where('recipient_type', RecipientType::Team)->count());

        // Trophy queries per recipient.
        $this->assertSame([$award->id], AchievementAward::query()->forTeam($red->team)->pluck('id')->all());
        $this->assertSame([$award->id], AchievementAward::query()->forSeasonTeam($red)->pluck('id')->all());
        $this->assertSame([$award->id], $red->team->awards()->pluck('id')->all());
        $this->assertSame([], AchievementAward::query()->forTeam($blue->team_id)->pluck('id')->all());
        $this->assertSame([], AchievementAward::query()->forSafetyCar()->pluck('id')->all());
        $this->assertFalse(AchievementAward::query()->forDriver($redCars[0]->driver_id)->whereKey($award->id)->exists());
        $this->assertSame(
            AchievementAward::query()->where('driver_id', $redCars[0]->driver_id)->pluck('id')->all(),
            $redCars[0]->driver->awards()->pluck('id')->all(),
        );
    }

    public function test_a_team_award_stays_out_of_the_oofscore(): void
    {
        $this->addDoublePodium();
        [$race] = $this->podiumRace();

        $this->runEngine($race);
        app(OofScoreStandings::class)->rebuild($race->round->season);

        $this->assertSame(AchievementShelf::Oof, $this->awards('double_podium')->sole()->shelf);
        $this->assertSame(
            AchievementAward::query()->where('recipient_type', RecipientType::Driver)->where('shelf', AchievementShelf::Oof)->where('status', AwardStatus::Final)->count(),
            (int) OofscoreStanding::query()->where('round_id', $race->round_id)->sum('awards_count'),
        );
    }

    public function test_the_sync_writes_the_recipient_and_the_safety_car_is_explicit(): void
    {
        $this->assertSame(RecipientType::SafetyCar, Achievement::firstWhere('key', 'took_the_lead')->recipient_type);
        $this->assertSame(RecipientType::SafetyCar, Achievement::firstWhere('key', 'frequent_flyer')->recipient_type);
        $this->assertSame(RecipientType::Driver, Achievement::firstWhere('key', 'did_the_thing')->recipient_type);

        Achievement::where('key', 'took_the_lead')->update(['recipient_type' => RecipientType::Driver]);
        app(CatalogueSync::class)->apply();

        $this->assertSame(RecipientType::SafetyCar, Achievement::firstWhere('key', 'took_the_lead')->recipient_type);
    }

    public function test_a_team_season_award_must_be_grouped_by_team(): void
    {
        config(['achievement_catalogue.achievements.team_flyer' => [
            ...config('achievement_catalogue.achievements.frequent_flyer'),
            'recipient' => 'team',
        ]]);

        $this->expectException(InvalidArgumentException::class);

        app(TriggerFactory::class)->for('team_flyer');
    }

    /**
     * A team achievement that is only catalogue data: both cars of a team
     * classified in the top three.
     */
    private function addDoublePodium(): void
    {
        config(['achievement_catalogue.achievements.double_podium' => [
            'name' => 'Double Podium',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Both cars of the team on the podium.',
            'supersedes' => [],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'team',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => [
                'type' => 'count', 'fact' => 'result', 'where' => ['classified_finisher', ['position', '<=', 3]],
                'at_least' => 2, 'instance' => 'double_podium', 'event' => 'team_podium', 'detail' => '{count} cars on the podium',
            ],
        ]]);

        app(CatalogueSync::class)->apply();
    }

    /**
     * A race with the red team first and second, the blue team third and fourth.
     *
     * @return array{0: RaceSession, 1: SeasonTeam, 2: SeasonTeam, 3: list<DriverEntry>}
     */
    private function podiumRace(): array
    {
        $race = $this->raceSession();
        $red = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $blue = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $redCars = [$this->car($race, 1, $red), $this->car($race, 11, $red)];
        $blueCars = [$this->car($race, 4, $blue), $this->car($race, 81, $blue)];

        foreach ([...$redCars, ...$blueCars] as $index => $car) {
            $position = $index + 1;
            $this->resultFor($car, ['position' => $position, 'position_text' => (string) $position, 'grid' => $position, 'status' => 'Finished', 'classification_order' => $position]);
        }

        return [$race, $red, $blue, $redCars];
    }
}
