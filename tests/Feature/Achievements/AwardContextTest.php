<?php

namespace Tests\Feature\Achievements;

use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\AwardScope;
use App\Enums\AwardSource;
use App\Enums\AwardStatus;
use App\Enums\RecipientType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\AchievementVariant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class AwardContextTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    public function test_a_stored_award_carries_its_context_recipient_snapshot_and_date(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 44);
        $this->resultFor($car, ['position' => 7, 'position_text' => '7', 'grid' => 7]);
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');
        AchievementVariant::create(['achievement_id' => Achievement::firstWhere('key', 'smile_for_the_camera')->id, 'driver_id' => $car->driver_id, 'name' => 'Say Cheese']);

        $this->runEngine($race);
        $award = $this->awards('smile_for_the_camera')->sole();

        $this->assertSame($race->round->season_id, $award->season_id);
        $this->assertSame($race->round_id, $award->round_id);
        $this->assertSame(AwardScope::Session, $award->scope);
        $this->assertSame("session:{$race->id}", $award->context_key);
        $this->assertSame(RecipientType::Driver, $award->recipient_type);
        $this->assertSame($car->season_team_id, $award->season_team_id);
        $this->assertSame($car->seasonTeam->team_id, $award->team_id);
        $this->assertSame($award->achievement->tier, $award->tier);
        $this->assertSame(AchievementShelf::Oof, $award->shelf);
        $this->assertSame($award->achievement->points(), $award->points);
        $this->assertSame('Say Cheese', $award->variant_name);
        $this->assertSame('Say Cheese', $award->achievementVariant->name);
        $this->assertSame('2024-05-05', $award->occurred_on->toDateString());
        $this->assertSame(505, $award->occurred_md);
        $this->assertSame(AwardSource::Engine, $award->source);
        $this->assertNotNull($award->finalized_at);
    }

    public function test_the_snapshot_stays_when_the_catalogue_changes_and_a_re_run_follows(): void
    {
        $race = $this->raceSession();
        $this->car($race, 44);
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');
        $this->runEngine($race);
        $award = $this->awards('smile_for_the_camera')->sole();
        $before = [$award->tier, $award->points];

        $award->achievement->update(['tier' => AchievementTier::Platinum]);
        $this->runEngine($race);

        $this->assertSame($before, [$award->fresh()->tier, $award->fresh()->points]);
        $this->assertNotSame($before[1], $award->achievement->fresh()->points());
    }

    public function test_finalized_at_is_set_once_and_never_cleared(): void
    {
        $award = AchievementAward::factory()->pending()->create();
        $this->assertNull($award->finalized_at);

        $award->update(['status' => AwardStatus::Final]);
        $finalizedAt = $award->fresh()->finalized_at;
        $this->assertNotNull($finalizedAt);

        $this->travel(2)->days();
        $award->suppress(User::factory()->create(), 'Not this one.');
        $award->update(['status' => AwardStatus::Final]);

        $this->assertEquals($finalizedAt, $award->fresh()->finalized_at);
    }

    public function test_confirming_another_car_moves_the_team_with_it(): void
    {
        $race = $this->raceSession();
        $first = $this->car($race, 1);
        $second = $this->car($race, 2);
        $award = AchievementAward::factory()->pending()->create(['race_session_id' => $race->id, 'driver_entry_id' => $first->id, 'driver_id' => $first->driver_id]);

        $award->confirm(User::factory()->create(), $second);

        $this->assertSame($second->season_team_id, $award->fresh()->season_team_id);
        $this->assertSame($second->seasonTeam->team_id, $award->fresh()->team_id);
    }

    public function test_a_safety_car_award_has_the_safety_car_as_recipient_and_no_tier(): void
    {
        config(['achievements.took_the_lead_laps' => 3]);
        $race = $this->raceSession();
        $this->resultFor($this->car($race, 1));
        $this->message($race, 'SAFETY CAR DEPLOYED', ['lap_number' => 3]);
        $this->message($race, 'SAFETY CAR IN THIS LAP', ['lap_number' => 7]);

        $this->runEngine($race);
        $award = $this->awards('took_the_lead')->sole();

        $this->assertSame(RecipientType::SafetyCar, $award->recipient_type);
        $this->assertNull($award->tier);
        $this->assertSame(0, $award->points);
        $this->assertNull($award->team_id);
    }

    public function test_the_calendar_window_spans_years_and_wraps_at_new_year(): void
    {
        $dates = ['2023-12-30', '2024-01-02', '1985-01-04', '2024-05-05', '1999-05-06'];
        $awards = collect($dates)->mapWithKeys(fn (string $date, int $index) => [$date => AchievementAward::factory()->create([
            'race_session_id' => $this->raceSession(round: $this->round((int) substr($date, 0, 4), $index + 1, ['date' => $date]))->id,
        ])]);

        $may = AchievementAward::query()->inCalendarWindow(CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-05-06'))->pluck('id');
        $newYear = AchievementAward::query()->inCalendarWindow(CarbonImmutable::parse('2026-12-28'), CarbonImmutable::parse('2027-01-03'))->pluck('id');

        $this->assertEqualsCanonicalizing([$awards['2024-05-05']->id, $awards['1999-05-06']->id], $may->all());
        $this->assertEqualsCanonicalizing([$awards['2023-12-30']->id, $awards['2024-01-02']->id], $newYear->all());
    }
}
