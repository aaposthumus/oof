<?php

namespace Tests\Feature\Achievements;

use App\Achievements\AchievementEngine;
use App\Achievements\Detection;
use App\Achievements\Rules\Finality;
use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\AchievementVariant;
use App\Models\DriverEntry;
use App\Models\MomentSensitivity;
use App\Models\RaceSession;
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

    private function lowConfidenceCase(): array
    {
        $race = $this->raceSession();
        $this->car($race, 16);
        $this->message($race, 'CAR 16 (LEC) STOPPED AT TURN 7');
        $this->message($race, 'RED FLAG', ['flag' => 'RED']);

        return [$race, 'whole_class_detention'];
    }

    public function test_a_historic_award_that_would_wait_for_review_is_suppressed_by_the_engine(): void
    {
        config(['achievements.review_from' => '2027-01-01']);
        [$race, $key] = $this->lowConfidenceCase();
        $this->resultFor($this->car($race, 44), ['status' => '+1 Lap', 'position' => 9, 'position_text' => '9']);

        $this->runEngine($race);

        $award = $this->awards($key)->sole();
        $this->assertSame(AwardStatus::Suppressed, $award->status);
        $this->assertSame('Historic, not reviewed', $award->decision_note);
        $this->assertSame(AwardReviewReason::LowConfidence, $award->review_reason);
        $this->assertNull($award->decided_by);
        $this->assertTrue($award->isSuppressedAsHistoric());
        $this->assertFalse($award->isDecided());
        $this->assertSame(AwardStatus::Final, $this->awards('lapped_dog')->sole()->status);
    }

    public function test_re_running_a_historic_session_neither_duplicates_nor_changes_the_suppression(): void
    {
        config(['achievements.review_from' => '2027-01-01']);
        [$race, $key] = $this->lowConfidenceCase();
        $this->runEngine($race);
        $award = $this->awards($key)->sole();
        $decidedAt = $award->decided_at;

        $this->travel(2)->hours();
        $stats = $this->runEngine($race);

        $this->assertCount(1, $this->awards($key));
        $this->assertEquals($decidedAt, $award->fresh()->decided_at);
        $this->assertSame(['found' => 1, 'created' => 0, 'updated' => 0, 'removed' => 0, 'pending' => 0], $stats[$key]);
    }

    public function test_a_historic_award_handed_back_stays_in_the_review_queue(): void
    {
        config(['achievements.review_from' => '2027-01-01']);
        [$race, $key] = $this->lowConfidenceCase();
        $this->runEngine($race);

        $this->awards($key)->sole()->restore();
        $this->assertSame(AwardStatus::Pending, $this->awards($key)->sole()->status);
        $this->runEngine($race);

        $award = $this->awards($key)->sole();
        $this->assertSame(AwardStatus::Pending, $award->status);
        $this->assertSame(AwardReviewReason::LowConfidence, $award->review_reason);
        $this->assertCount(1, $this->awards($key));
    }

    public function test_a_historic_award_suppressed_by_a_person_keeps_their_reason(): void
    {
        config(['achievements.review_from' => '2027-01-01']);
        [$race, $key] = $this->lowConfidenceCase();
        $this->runEngine($race);

        $this->awards($key)->sole()->suppress(User::factory()->create(), 'Not funny.');
        $this->runEngine($race);

        $award = $this->awards($key)->sole();
        $this->assertSame('Not funny.', $award->decision_note);
        $this->assertFalse($award->isSuppressedAsHistoric());
    }

    public function test_an_automatically_suppressed_award_is_removed_when_no_longer_found(): void
    {
        config(['achievements.review_from' => '2027-01-01']);
        [$race, $key] = $this->lowConfidenceCase();
        $this->runEngine($race);

        $race->raceControlMessages()->where('message', 'RED FLAG')->delete();
        $this->runEngine($race);

        $this->assertCount(0, $this->awards($key));
    }

    public function test_sessions_from_the_review_date_onwards_behave_as_before(): void
    {
        config(['achievements.review_from' => '2024-05-05']);
        [$race, $key] = $this->lowConfidenceCase();

        $this->runEngine($race);

        $this->assertSame(AwardStatus::Pending, $this->awards($key)->sole()->status);
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

    public function test_nothing_to_see_here_counts_every_award_unless_a_person_suppressed_it(): void
    {
        $race = $this->raceSession();
        $qualifying = $this->raceSession(SessionType::Qualifying, $race->round);
        $earlier = $this->raceSession(SessionType::Race, $this->round(2024, 4));
        $this->resultFor($this->car($race, 99), ['position' => 20, 'position_text' => '20', 'classification_order' => 20]);
        $cars = collect([1, 2, 3, 4, 5])->mapWithKeys(function (int $number) use ($race, $earlier) {
            $entry = $this->car($race, $number);
            $this->resultFor($entry, ['position' => $number + 5, 'position_text' => (string) ($number + 5), 'classification_order' => $number + 5]);
            $this->resultFor($this->car($earlier, $number, null, $entry->driver), ['position' => $number, 'position_text' => (string) $number]);

            return [$number => $entry];
        });
        $award = fn (int $number, array $attributes) => AchievementAward::factory()->create([
            'race_session_id' => $qualifying->id,
            'achievement_id' => Achievement::firstWhere('key', 'spare_tyre')->id,
            'driver_id' => $cars[$number]->driver_id,
            ...$attributes,
        ]);
        $award(2, ['status' => AwardStatus::Pending]);
        $award(3, ['status' => AwardStatus::Suppressed, 'decided_at' => now(), 'decision_note' => AchievementAward::HISTORIC_NOTE]);
        $award(4, ['status' => AwardStatus::Suppressed, 'decided_by' => User::factory()->create()->id, 'decided_at' => now(), 'decision_note' => 'No.']);

        $this->runEngine($race);

        $quiet = $this->awards('nothing_to_see_here')->pluck('driver_entry_id')->sort()->values()->all();
        $this->assertSame([$cars[1]->id, $cars[4]->id, $cars[5]->id], $quiet);
    }

    public function test_nothing_to_see_here_ignores_a_suppression_by_a_person_in_the_same_session(): void
    {
        $race = $this->raceSession();
        $earlier = $this->raceSession(SessionType::Race, $this->round(2024, 4));
        $this->resultFor($this->car($race, 99), ['position' => 20, 'position_text' => '20', 'status' => '+1 Lap']);
        $entry = $this->car($race, 44);
        $this->resultFor($entry, ['position' => 9, 'position_text' => '9', 'status' => '+1 Lap']);
        $this->resultFor($this->car($earlier, 44, null, $entry->driver), ['position' => 5, 'position_text' => '5']);

        $this->runEngine($race);
        $this->assertCount(0, $this->awards('nothing_to_see_here'));

        $this->awards('lapped_dog')->firstWhere('driver_entry_id', $entry->id)->suppress(User::factory()->create(), 'Not this time.');
        $this->runEngine($race);

        $this->assertCount(1, $this->awards('nothing_to_see_here'));
    }

    public function test_nothing_to_see_here_never_goes_to_a_first_ever_start(): void
    {
        $race = $this->raceSession();
        $debutant = $this->car($race, 38);
        $this->resultFor($debutant, ['position' => 10, 'position_text' => '10', 'points' => 1]);

        $this->runEngine($race);

        $this->assertCount(0, $this->awards('nothing_to_see_here'));
    }

    /**
     * A race with one retirement (car 16) and one clean finisher (car 44).
     *
     * @return array{0: RaceSession, 1: DriverEntry, 2: DriverEntry}
     */
    private function raceWithARetirement(): array
    {
        $race = $this->raceSession();
        $retired = $this->car($race, 16);
        $clean = $this->car($race, 44);
        $this->resultFor($retired, ['status' => 'Accident', 'position' => null, 'position_text' => 'R', 'is_classified' => false, 'laps_completed' => 10]);
        $this->resultFor($clean, ['position' => 8, 'position_text' => '8']);

        return [$race, $retired, $clean];
    }

    public function test_a_retirement_with_a_red_flag_sends_the_oof_awards_to_review(): void
    {
        [$race, $retired] = $this->raceWithARetirement();
        $this->message($race, 'CAR 16 (BOT) STOPPED AT TURN 7');
        $this->message($race, 'RED FLAG', ['category' => 'Flag', 'flag' => 'RED']);

        $this->runEngine($race);

        $award = $this->awards('home_before_dinner')->sole();
        $this->assertSame($retired->id, $award->driver_entry_id);
        $this->assertSame(AwardStatus::Pending, $award->status);
        $this->assertSame(AwardReviewReason::HeavyCrash, $award->review_reason);
    }

    public function test_the_medical_car_is_a_heavy_crash(): void
    {
        [$race] = $this->raceWithARetirement();
        $this->message($race, 'CAR 16 (BOT) STOPPED AT TURN 7');
        $this->message($race, 'MEDICAL CAR DEPLOYED');

        $this->runEngine($race);

        $this->assertSame(AwardReviewReason::HeavyCrash, $this->awards('home_before_dinner')->sole()->review_reason);
    }

    public function test_a_car_stopped_for_a_long_time_is_a_heavy_crash(): void
    {
        [$race] = $this->raceWithARetirement();
        $this->message($race, 'CAR 16 (BOT) STOPPED AT TURN 7');
        $this->message($race, 'CLEAR IN TRACK SECTOR 7', ['category' => 'Flag', 'flag' => 'CLEAR']);
        config(['achievements.heavy_crash_stopped_seconds' => 50]);

        $this->runEngine($race);

        $this->assertSame(AwardReviewReason::HeavyCrash, $this->awards('home_before_dinner')->sole()->review_reason);
    }

    public function test_an_ordinary_retirement_and_other_drivers_stay_final(): void
    {
        [$race] = $this->raceWithARetirement();
        $this->message($race, 'CAR 16 (BOT) STOPPED AT TURN 7');
        $this->message($race, 'CLEAR IN TRACK SECTOR 7', ['category' => 'Flag', 'flag' => 'CLEAR']);

        $this->runEngine($race);

        $award = $this->awards('home_before_dinner')->sole();
        $this->assertSame(AwardStatus::Final, $award->status);
        $this->assertNull($award->review_reason);
    }

    public function test_a_heavy_crash_only_reviews_the_retired_drivers_oof_awards(): void
    {
        [$race, $retired, $clean] = $this->raceWithARetirement();
        $this->message($race, 'CAR 16 (BOT) STOPPED AT TURN 7');
        $this->message($race, 'RED FLAG', ['category' => 'Flag', 'flag' => 'RED']);
        $finality = app(Finality::class);
        $judge = fn (string $key, $entry) => $finality->judge(
            new Detection($key, $entry->id, 'x', 'x', ['source' => 'results']),
            $this->data($race),
            true,
            $entry->driver_id,
            Achievement::firstWhere('key', $key),
        );

        $this->assertSame(AwardReviewReason::HeavyCrash, $judge('home_before_dinner', $retired)[1]);
        $this->assertSame(AwardReviewReason::HeavyCrash, $judge('bumper_cars', $retired)[1]);
        $this->assertNull($judge('spare_tyre', $retired)[1]);
        $this->assertNull($judge('home_before_dinner', $clean)[1]);
    }
}
