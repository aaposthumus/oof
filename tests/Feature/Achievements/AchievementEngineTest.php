<?php

namespace Tests\Feature\Achievements;

use App\Achievements\AchievementEngine;
use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\Achievement;
use App\Models\AchievementVariant;
use App\Models\MomentSensitivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    public function test_re_running_a_session_updates_its_awards_without_duplicating_them(): void
    {
        $race = $this->raceSession();
        $this->resultFor($this->car($race, 44), ['position' => 7, 'position_text' => '7', 'grid' => 7]);
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');

        $this->runEngine($race);
        $ids = $this->awards()->pluck('id')->all();
        $stats = $this->runEngine($race);

        $this->assertSame($ids, $this->awards()->pluck('id')->all());
        $this->assertSame(['found' => 1, 'created' => 0, 'updated' => 0, 'removed' => 0, 'pending' => 0], $stats['smile_for_the_camera']);
    }

    public function test_never_undoes_a_suppression_even_when_the_award_is_no_longer_found(): void
    {
        $race = $this->raceSession();
        $this->car($race, 44);
        $message = $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');
        $this->runEngine($race);
        $award = $this->awards('smile_for_the_camera')->sole();

        $award->suppress(User::factory()->create(), 'Not funny this weekend.');
        $this->runEngine($race);
        $this->assertSame(AwardStatus::Suppressed, $award->fresh()->status);

        $message->delete();
        $this->runEngine($race);
        $this->assertSame(AwardStatus::Suppressed, $award->fresh()->status);
        $this->assertSame('Not funny this weekend.', $award->fresh()->decision_note);
    }

    public function test_removes_awards_that_are_no_longer_found(): void
    {
        $race = $this->raceSession();
        $this->car($race, 44);
        $message = $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');
        $this->runEngine($race);

        $message->delete();
        $stats = $this->runEngine($race);

        $this->assertCount(0, $this->awards());
        $this->assertSame(1, $stats['smile_for_the_camera']['removed']);
    }

    public function test_an_offence_absorbs_the_incident_note_and_its_penalty_as_detail(): void
    {
        $race = $this->raceSession();
        $this->car($race, 87);
        $this->car($race, 10);
        $this->message($race, 'TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) NOTED - CAUSING A COLLISION');
        $this->message($race, 'FIA STEWARDS: TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) UNDER INVESTIGATION - CAUSING A COLLISION');
        $this->message($race, 'FIA STEWARDS: 10 SECOND TIME PENALTY FOR CAR 87 (BEA) - CAUSING A COLLISION');

        $this->runEngine($race);

        $bumperCars = $this->awards('bumper_cars')->sole();
        $this->assertSame(87, $bumperCars->driverEntry->car_number);
        $this->assertSame('Bumper Cars · 10 s', $bumperCars->title());
        $this->assertCount(3, $bumperCars->evidence['messages']);
        $this->assertCount(0, $this->awards('perfect_ten'));
        $this->assertSame([10], $this->awards('it_was_like_that_when_i_got_there')->map->driverEntry->pluck('car_number')->all());
    }

    public function test_separate_events_still_stack(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 87);
        $this->car($race, 10);
        $this->message($race, 'TURN 1 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) NOTED - CAUSING A COLLISION');
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 87 (BEA) - CAUSING A COLLISION');
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 87 (BEA) - SPEEDING IN THE PIT LANE');
        $this->resultFor($car, ['position' => 15, 'position_text' => '15', 'grid' => 15, 'status' => '+1 Lap']);

        $this->runEngine($race);

        $keys = $this->awards()->where('driver_entry_id', $car->id)->pluck('achievement.key');
        $this->assertEqualsCanonicalizing(['bumper_cars', 'smile_for_the_camera', 'lapped_dog'], $keys->intersect(['bumper_cars', 'smile_for_the_camera', 'lapped_dog', 'high_five', 'it_was_like_that_when_i_got_there'])->values()->all());
    }

    public function test_a_track_limits_penalty_replaces_the_deletions_and_the_black_and_white_flag(): void
    {
        $race = $this->raceSession();
        $this->car($race, 18);
        $this->message($race, 'CAR 18 (STR) TIME 1:07.312 DELETED - TRACK LIMITS AT TURN 10 LAP 12 15:30:01');
        $this->message($race, 'BLACK AND WHITE FLAG FOR CAR 18 (STR) - TRACK LIMITS');
        $this->message($race, 'CAR 18 (STR) TIME 1:07.901 DELETED - TRACK LIMITS AT TURN 10 LAP 30 15:58:12');
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 18 (STR) - TRACK LIMITS (5TH OFFENCE)');

        $this->runEngine($race);

        $this->assertSame(['high_five'], $this->awards()->pluck('achievement.key')->all());
    }

    public function test_good_boy_replaces_lapped_dog_and_a_non_stacking_achievement_is_awarded_once(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 2);
        $this->resultFor($car, ['position' => 18, 'position_text' => '18', 'grid' => 18, 'status' => '+2 Laps']);
        $this->message($race, 'CAR 2 (SAR) TIME 1:31.001 DELETED - TRACK LIMITS AT TURN 4 LAP 3 15:10:00');
        $this->message($race, 'CAR 2 (SAR) TIME 1:31.555 DELETED - TRACK LIMITS AT TURN 4 LAP 8 15:20:00');

        $this->runEngine($race);

        $keys = $this->awards()->pluck('achievement.key');
        $this->assertContains('good_boy', $keys);
        $this->assertNotContains('lapped_dog', $keys);
        $this->assertSame('×2', $this->awards('colouring_outside_the_lines')->sole()->detail);
        $this->assertCount(2, $this->awards('colouring_outside_the_lines')->sole()->evidence['messages']);
    }

    public function test_involvement_never_implies_blame(): void
    {
        $race = $this->raceSession();
        $this->car($race, 87);
        $this->car($race, 10);
        $this->message($race, 'TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) NOTED - CAUSING A COLLISION');
        $this->message($race, 'FIA STEWARDS: TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) REVIEWED NO FURTHER INVESTIGATION - CAUSING A COLLISION');

        $this->runEngine($race);

        $this->assertSame(['it_was_like_that_when_i_got_there'], $this->awards()->pluck('achievement.key')->unique()->values()->all());
        $this->assertCount(2, $this->awards());
        $this->assertSame([AwardConfidence::Medium], $this->awards()->pluck('confidence')->unique()->values()->all());
    }

    public function test_a_fault_based_award_needs_a_message_naming_exactly_one_car(): void
    {
        $race = $this->raceSession();
        $this->car($race, 87);
        $this->car($race, 10);
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CARS 87 (BEA) AND 10 (GAS) - CAUSING A COLLISION');

        $this->runEngine($race);

        $this->assertCount(0, $this->awards('bumper_cars'));
        $this->assertCount(0, $this->awards('high_five'));
    }

    public function test_an_award_tied_to_an_open_investigation_waits_for_the_outcome(): void
    {
        $race = $this->raceSession();
        $this->resultFor($this->car($race, 87), ['position' => 1, 'position_text' => '1']);
        $this->resultFor($this->car($race, 10), ['position' => 2, 'position_text' => '2']);
        $this->message($race, 'TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) NOTED - CAUSING A COLLISION');
        $this->message($race, 'FIA STEWARDS: TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) WILL BE INVESTIGATED AFTER THE RACE - CAUSING A COLLISION');
        $duringTheWeekend = CarbonImmutable::parse('2024-05-05 23:30:00');

        $this->runEngine($race, $duringTheWeekend);
        $involvement = $this->awards('it_was_like_that_when_i_got_there');
        $this->assertSame([AwardStatus::Pending], $involvement->pluck('status')->unique()->values()->all());
        $this->assertSame([AwardReviewReason::OpenInvestigation], $involvement->pluck('review_reason')->unique()->values()->all());

        $this->message($race, 'FIA STEWARDS: TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) REVIEWED NO FURTHER ACTION - CAUSING A COLLISION');
        $this->runEngine($race, $duringTheWeekend->addDays(2));

        $this->assertSame([AwardStatus::Final], $this->awards('it_was_like_that_when_i_got_there')->pluck('status')->unique()->values()->all());
    }

    public function test_awards_wait_until_the_session_is_final(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 44);
        $this->resultFor($car, ['status' => '+1 Lap']);

        $this->runEngine($race, CarbonImmutable::parse('2024-05-06 12:00:00'));
        $this->assertSame(AwardStatus::Pending, $this->awards('lapped_dog')->sole()->status);
        $this->assertNull($this->awards('lapped_dog')->sole()->review_reason);

        $this->runEngine($race, CarbonImmutable::parse('2024-05-06 23:30:00'));
        $this->assertSame(AwardStatus::Final, $this->awards('lapped_dog')->sole()->status);
    }

    public function test_low_confidence_awards_always_wait_for_a_person(): void
    {
        $race = $this->raceSession();
        $this->car($race, 16);
        $this->message($race, 'CAR 16 (LEC) STOPPED AT TURN 7', ['category' => 'Other']);
        $this->message($race, 'RED FLAG', ['category' => 'Flag', 'flag' => 'RED']);

        $this->runEngine($race);

        $award = $this->awards('whole_class_detention')->sole();
        $this->assertSame(16, $award->driverEntry->car_number);
        $this->assertSame(AwardConfidence::Low, $award->confidence);
        $this->assertSame(AwardStatus::Pending, $award->status);
        $this->assertSame(AwardReviewReason::LowConfidence, $award->review_reason);
    }

    public function test_a_disagreement_with_jolpica_is_flagged_instead_of_awarded(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 27);
        $this->resultFor($car, ['status' => 'Finished']);
        $this->message($race, 'BLACK FLAG FOR CAR 27 (HUL)', ['category' => 'Flag', 'flag' => 'BLACK']);

        $this->runEngine($race);

        $award = $this->awards('expelled')->sole();
        $this->assertSame(AwardStatus::Pending, $award->status);
        $this->assertSame(AwardReviewReason::SourceDisagreement, $award->review_reason);
        $this->assertStringContainsString('Finished', $award->evidence['note']);
    }

    public function test_a_car_picked_by_hand_survives_a_re_run(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 16);
        $this->message($race, 'RED FLAG', ['category' => 'Flag', 'flag' => 'RED']);
        $this->runEngine($race);
        $award = $this->awards('whole_class_detention')->sole();
        $this->assertNull($award->driver_entry_id);
        $this->assertSame(AwardReviewReason::NoClearCause, $award->review_reason);

        $award->confirm(User::factory()->create(), $car);
        $this->runEngine($race);

        $this->assertSame($car->id, $award->fresh()->driver_entry_id);
        $this->assertSame(AwardStatus::Final, $award->fresh()->status);
    }

    public function test_awards_on_a_sensitive_weekend_wait_for_a_person(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 44);
        $this->resultFor($car, ['status' => '+1 Lap']);
        MomentSensitivity::factory()->create(['round_id' => $race->round_id, 'driver_id' => null]);

        $this->runEngine($race);

        $this->assertSame(AwardReviewReason::SensitiveWeekend, $this->awards('lapped_dog')->sole()->review_reason);
    }

    public function test_applies_a_drivers_variant_name(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 44);
        $this->resultFor($car, ['status' => '+1 Lap']);
        AchievementVariant::create(['achievement_id' => Achievement::firstWhere('key', 'lapped_dog')->id, 'driver_id' => $car->driver_id, 'name' => 'Sir Lapalot']);

        $this->runEngine($race);

        $this->assertSame('Sir Lapalot · 1 lap', $this->awards('lapped_dog')->sole()->title());
    }

    public function test_only_runs_enabled_achievements_for_their_session_types(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $this->car($qualifying, 44);
        $this->message($qualifying, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');
        Achievement::where('key', 'smile_for_the_camera')->update(['is_enabled' => false]);

        $this->runEngine($qualifying);

        $this->assertCount(0, $this->awards('smile_for_the_camera'));
        $this->assertCount(0, $this->awards('high_five'));
    }

    public function test_a_dry_run_stores_nothing(): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 44);
        $this->resultFor($car, ['status' => '+1 Lap']);

        $stats = app(AchievementEngine::class)->run($race, dryRun: true, now: CarbonImmutable::parse('2024-06-01'));

        $this->assertSame(1, $stats['lapped_dog']['created']);
        $this->assertCount(0, $this->awards());
        $this->assertNull($race->fresh()->achievements_checked_at);
    }
}
