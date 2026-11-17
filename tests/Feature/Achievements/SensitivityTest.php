<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Rules\Sensitivity;
use App\Achievements\WithheldOnSensitiveWeekend;
use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySource;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use App\Models\MomentSensitivity;
use App\Models\RaceSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class SensitivityTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private RaceSession $race;

    private DriverEntry $winner;

    private DriverEntry $retired;

    private DriverEntry $offender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        config(['achievements.took_the_lead_laps' => 3]);
    }

    /**
     * A race with a win, a retirement, a lapped car with a collision penalty
     * and a long safety car: glory, crash-related, OOF and Safety Car awards.
     */
    private function weekend(): void
    {
        $this->race = $this->raceSession();
        $this->winner = $this->car($this->race, 1);
        $this->retired = $this->car($this->race, 16);
        $this->offender = $this->car($this->race, 44);
        $this->resultFor($this->winner, ['position' => 1, 'position_text' => '1', 'classification_order' => 1, 'grid' => 1, 'status' => 'Finished', 'laps_completed' => 57]);
        $this->resultFor($this->offender, ['position' => 2, 'position_text' => '2', 'classification_order' => 2, 'grid' => 2, 'status' => '+1 Lap', 'laps_completed' => 56]);
        $this->resultFor($this->retired, ['position' => null, 'position_text' => 'R', 'classification_order' => 3, 'grid' => 3, 'is_classified' => false, 'status' => 'Engine', 'laps_completed' => 30]);
        $this->message($this->race, 'SAFETY CAR DEPLOYED', ['lap_number' => 3]);
        $this->message($this->race, 'SAFETY CAR IN THIS LAP', ['lap_number' => 7]);
        $this->message($this->race, 'FIA STEWARDS: 10 SECOND TIME PENALTY FOR CAR 44 (HAM) - CAUSING A COLLISION');
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        return $this->awards()->map(fn (AchievementAward $award) => $award->achievement->key)->sort()->values()->all();
    }

    public function test_without_a_sensitivity_every_kind_of_award_is_given(): void
    {
        $this->weekend();

        $this->runEngine($this->race);

        $this->assertSame(['bumper_cars', 'did_the_thing', 'home_before_dinner', 'lapped_dog', 'took_the_lead'], array_values(array_intersect($this->keys(), ['bumper_cars', 'did_the_thing', 'home_before_dinner', 'lapped_dog', 'took_the_lead'])));
        $this->assertFalse($this->awards()->contains('is_sensitive', true));
    }

    public function test_crash_related_achievements_and_the_safety_car_are_never_awarded_on_a_sensitive_weekend(): void
    {
        $this->weekend();
        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);

        $this->runEngine($this->race);

        $withheld = Achievement::query()->where('withheld_on_sensitive_weekends', true)->pluck('key')->all();
        $this->assertSame([], array_values(array_intersect($this->keys(), $withheld)));
        $this->assertNotContains('perfect_ten', $this->keys(), 'The collision penalty goes with the withheld offence.');
        $this->assertContains('lapped_dog', $this->keys());
    }

    public function test_an_existing_withheld_award_is_removed_or_force_suppressed_when_a_person_decided_it(): void
    {
        $this->weekend();
        $this->runEngine($this->race);
        $decided = $this->awards('home_before_dinner')->sole();
        $decided->confirm(User::factory()->create());
        $undecided = $this->awards('took_the_lead')->sole();

        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);
        $this->runEngine($this->race);

        $this->assertModelMissing($undecided);
        $decided->refresh();
        $this->assertSame(AwardStatus::Suppressed, $decided->status);
        $this->assertSame(Sensitivity::SYSTEM_NOTE, $decided->decision_note);
        $this->assertNull($decided->decided_by);
        $this->assertTrue($decided->is_sensitive);

        $this->runEngine($this->race);
        $this->assertSame(AwardStatus::Suppressed, $decided->fresh()->status);
    }

    public function test_a_blocked_round_stores_nothing_and_suppresses_what_a_person_decided(): void
    {
        $this->weekend();
        $this->runEngine($this->race);
        $win = $this->awards('did_the_thing')->sole();
        $win->confirm(User::factory()->create());

        MomentSensitivity::factory()->blocked()->create(['round_id' => $this->race->round_id]);
        $this->runEngine($this->race);

        $this->assertSame([$win->id], $this->awards()->pluck('id')->all());
        $win->refresh();
        $this->assertSame(AwardStatus::Suppressed, $win->status);
        $this->assertSame(Sensitivity::SYSTEM_NOTE, $win->decision_note);
        $this->assertSame(AwardStatus::Final->value, $win->held_decision['status']);
        $this->assertSame(0, AchievementAward::query()->publishable()->count());
    }

    public function test_a_blocked_driver_stores_nothing_for_that_driver_and_the_round_turns_sensitive(): void
    {
        $this->weekend();
        MomentSensitivity::factory()->blocked()->create(['round_id' => $this->race->round_id, 'driver_id' => $this->offender->driver_id]);

        $this->runEngine($this->race);

        $this->assertFalse($this->awards()->contains('driver_id', $this->offender->driver_id));
        $this->assertSame(AwardStatus::Final, $this->awards('did_the_thing')->sole()->status);
        $this->assertTrue($this->awards()->every('is_sensitive', true));
        $this->assertNotContains('home_before_dinner', $this->keys());
    }

    public function test_glory_on_a_sensitive_weekend_is_final_and_flagged_while_oof_waits_for_a_person(): void
    {
        $this->weekend();
        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id, 'note' => 'A marshal was injured.']);

        $this->runEngine($this->race);

        $win = $this->awards('did_the_thing')->sole();
        $this->assertSame(AwardStatus::Final, $win->status);
        $this->assertTrue($win->is_sensitive);
        $this->assertSame('A marshal was injured.', $win->sensitivity_note);
        $lapped = $this->awards('lapped_dog')->sole();
        $this->assertSame(AwardStatus::Pending, $lapped->status);
        $this->assertSame(AwardReviewReason::SensitiveWeekend, $lapped->review_reason);
        $this->assertTrue($lapped->is_sensitive);
    }

    public function test_a_sensitivity_naming_one_driver_reaches_the_whole_round(): void
    {
        $this->weekend();
        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id, 'driver_id' => $this->retired->driver_id]);

        $this->runEngine($this->race);

        $this->assertTrue($this->awards('did_the_thing')->sole()->is_sensitive);
        $this->assertNotContains('took_the_lead', $this->keys());
    }

    public function test_the_flag_never_clears(): void
    {
        $this->weekend();
        $sensitivity = MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);
        $this->runEngine($this->race);
        $win = $this->awards('did_the_thing')->sole();

        $sensitivity->delete();
        $this->runEngine($this->race);
        $win->update(['is_sensitive' => false]);

        $this->assertTrue($win->fresh()->is_sensitive);
        $this->assertContains('took_the_lead', $this->keys(), 'Withheld awards come back once the weekend is no longer sensitive.');
    }

    public function test_a_change_to_a_sensitivity_sends_the_round_back_to_the_engine(): void
    {
        $this->weekend();
        $this->runEngine($this->race);
        $this->assertNotNull($this->race->fresh()->achievements_checked_at);

        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);

        $this->assertNull($this->race->fresh()->achievements_checked_at);
    }

    public function test_built_different_can_never_be_awarded_on_a_sensitive_weekend(): void
    {
        $this->weekend();
        $builtDifferent = Achievement::factory()->create(['key' => 'built_different', 'section' => AchievementSection::Glory, 'shelf' => AchievementShelf::Glory, 'withheld_on_sensitive_weekends' => true]);
        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);

        $this->expectException(WithheldOnSensitiveWeekend::class);

        AchievementAward::factory()->create(['achievement_id' => $builtDifferent->id, 'race_session_id' => $this->race->id, 'driver_entry_id' => $this->winner->id, 'driver_id' => $this->winner->driver_id]);
    }

    public function test_built_different_can_be_awarded_on_an_ordinary_weekend(): void
    {
        $this->weekend();
        $builtDifferent = Achievement::factory()->create(['key' => 'built_different', 'section' => AchievementSection::Glory, 'shelf' => AchievementShelf::Glory, 'withheld_on_sensitive_weekends' => true]);

        $award = AchievementAward::factory()->create(['achievement_id' => $builtDifferent->id, 'race_session_id' => $this->race->id, 'driver_entry_id' => $this->winner->id, 'driver_id' => $this->winner->driver_id]);

        $this->assertModelExists($award);
    }

    /**
     * Car 16 stops, then a red flag; optionally the medical car, and the restart after the given minutes.
     */
    private function heavyCrash(bool $medicalCar, int $restartAfterMinutes = 5): void
    {
        $this->retired->result->update(['status' => 'Accident']);
        $stopped = $this->message($this->race, 'CAR 16 (LEC) STOPPED AT TURN 7');
        $red = $this->message($this->race, 'RED FLAG', ['category' => 'Flag', 'flag' => 'RED']);

        if ($medicalCar) {
            $this->message($this->race, 'MEDICAL CAR DEPLOYED');
        }

        $this->message($this->race, 'SESSION STARTED', ['category' => 'SessionStatus', 'issued_at' => $red->issued_at->addMinutes($restartAfterMinutes)]);
        $this->assertNotNull($stopped);
    }

    public function test_a_red_flag_with_the_medical_car_on_a_live_weekend_proposes_a_sensitivity_that_acts_at_once(): void
    {
        config(['achievements.sensitivity.propose_from' => '2024-01-01']);
        $this->weekend();
        $this->heavyCrash(medicalCar: true);

        $this->runEngine($this->race);

        $proposal = MomentSensitivity::query()->sole();
        $this->assertSame(SensitivitySource::Engine, $proposal->source);
        $this->assertSame(SensitivityLevel::Sensitive, $proposal->level);
        $this->assertNull($proposal->driver_id);
        $this->assertSame([$this->retired->driver_id], $proposal->involved_driver_ids);
        $this->assertTrue($proposal->isProposal());
        $this->assertStringContainsString('red flag and medical car', $proposal->note);
        $this->assertNotContains('took_the_lead', $this->keys());
        $this->assertTrue($this->awards('did_the_thing')->sole()->is_sensitive);
        $this->assertStringContainsString('Proposed, not yet confirmed', $this->awards('did_the_thing')->sole()->sensitivity_note);
    }

    public function test_one_proposal_per_round_names_every_driver_involved(): void
    {
        config(['achievements.sensitivity.propose_from' => '2024-01-01']);
        $this->weekend();
        $second = $this->car($this->race, 22);
        $this->resultFor($second, ['position' => null, 'position_text' => 'R', 'classification_order' => 4, 'grid' => 4, 'is_classified' => false, 'status' => 'Accident', 'laps_completed' => 30]);
        $this->heavyCrash(medicalCar: true);
        $red = $this->race->raceControlMessages()->where('message', 'RED FLAG')->sole();
        $this->message($this->race, 'CAR 22 (TSU) STOPPED AT TURN 7', ['issued_at' => $red->issued_at->subSeconds(20)]);

        $this->runEngine($this->race);
        $this->runEngine($this->race);

        $proposal = MomentSensitivity::query()->sole();
        $this->assertEqualsCanonicalizing([$this->retired->driver_id, $second->driver_id], $proposal->involved_driver_ids);
        $this->assertStringContainsString($this->retired->driver->family_name, $proposal->note);
        $this->assertStringContainsString($second->driver->family_name, $proposal->note);
    }

    public function test_a_long_red_flag_stoppage_proposes_a_sensitivity_and_a_short_one_does_not(): void
    {
        config(['achievements.sensitivity.propose_from' => '2024-01-01', 'achievements.sensitivity.long_stoppage_minutes' => 20]);
        $this->weekend();
        $this->heavyCrash(medicalCar: false, restartAfterMinutes: 10);

        $this->runEngine($this->race);
        $this->assertSame(0, MomentSensitivity::count());

        $this->race->raceControlMessages()->where('message', 'SESSION STARTED')->update(['issued_at' => CarbonImmutable::parse('2024-05-05 20:45:00')]);
        $this->runEngine($this->race);

        $this->assertStringContainsString('red flag, stopped for', MomentSensitivity::query()->sole()->note);
    }

    public function test_no_proposal_for_a_weekend_before_proposals_start(): void
    {
        config(['achievements.sensitivity.propose_from' => '2027-01-01']);
        $this->weekend();
        $this->heavyCrash(medicalCar: true);

        $this->runEngine($this->race);

        $this->assertSame(0, MomentSensitivity::count());
        $this->assertContains('took_the_lead', $this->keys());
    }

    public function test_a_dismissed_proposal_stops_counting_and_is_not_proposed_again(): void
    {
        config(['achievements.sensitivity.propose_from' => '2024-01-01']);
        $this->weekend();
        $this->heavyCrash(medicalCar: true);
        $this->runEngine($this->race);

        MomentSensitivity::query()->sole()->dismiss();
        $this->runEngine($this->race);

        $this->assertSame(1, MomentSensitivity::count());
        $this->assertFalse(Sensitivity::forRound($this->race->round_id)['sensitive']);
        $this->assertContains('took_the_lead', $this->keys());
        $this->assertSame(AwardStatus::Final, $this->awards('lapped_dog')->sole()->status);
        $this->assertTrue($this->awards('did_the_thing')->sole()->is_sensitive, 'The flag never clears.');
    }

    public function test_dismissing_the_last_sensitivity_gives_back_what_a_person_had_decided(): void
    {
        config(['achievements.sensitivity.propose_from' => '2024-01-01']);
        $this->weekend();
        $this->runEngine($this->race);
        $person = User::factory()->create();
        $decided = $this->awards('home_before_dinner')->sole();
        $decided->confirm($person);
        $this->heavyCrash(medicalCar: true);
        $this->runEngine($this->race);
        $this->assertSame(AwardStatus::Suppressed, $decided->fresh()->status);

        MomentSensitivity::query()->sole()->dismiss();

        $decided->refresh();
        $this->assertSame(AwardStatus::Final, $decided->status);
        $this->assertSame($person->id, $decided->decided_by);
        $this->assertNull($decided->held_decision);
        $this->assertTrue($decided->is_sensitive, 'The flag never clears.');
    }

    public function test_dismissing_a_proposal_keeps_the_suppression_while_another_sensitivity_covers_the_round(): void
    {
        config(['achievements.sensitivity.propose_from' => '2024-01-01']);
        $this->weekend();
        $this->runEngine($this->race);
        $decided = $this->awards('home_before_dinner')->sole();
        $decided->confirm(User::factory()->create());
        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);
        $this->heavyCrash(medicalCar: true);
        $this->runEngine($this->race);

        MomentSensitivity::query()->where('source', SensitivitySource::Engine)->sole()->dismiss();

        $this->assertSame(AwardStatus::Suppressed, $decided->fresh()->status);
        $this->assertNotNull($decided->fresh()->held_decision);
    }

    public function test_deleting_a_block_gives_back_what_a_person_had_decided(): void
    {
        $this->weekend();
        $this->runEngine($this->race);
        $win = $this->awards('did_the_thing')->sole();
        $win->confirm(User::factory()->create());
        $block = MomentSensitivity::factory()->blocked()->create(['round_id' => $this->race->round_id]);
        $this->runEngine($this->race);

        $block->delete();

        $this->assertSame(AwardStatus::Final, $win->fresh()->status);
    }

    public function test_the_added_offences_are_withheld_and_the_others_are_not(): void
    {
        $withheld = Achievement::query()->where('withheld_on_sensitive_weekends', true)->pluck('key');

        foreach (['released_into_the_wild', 'failed_the_driving_test', 'selective_vision', 'queue_jumper'] as $key) {
            $this->assertContains($key, $withheld);
        }

        foreach (['reverse_gear', 'sent_to_your_room', 'expelled'] as $key) {
            $this->assertNotContains($key, $withheld);
        }
    }

    public function test_an_unsafe_release_is_not_awarded_on_a_sensitive_weekend(): void
    {
        $this->weekend();
        $this->message($this->race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 1 (VER) - UNSAFE RELEASE');
        $this->runEngine($this->race);
        $this->assertContains('released_into_the_wild', $this->keys());

        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);
        $this->runEngine($this->race);

        $this->assertNotContains('released_into_the_wild', $this->keys());
    }

    public function test_only_final_awards_without_a_flag_are_publishable(): void
    {
        $final = AchievementAward::factory()->create();
        AchievementAward::factory()->pending()->create();
        AchievementAward::factory()->create(['status' => AwardStatus::Suppressed]);
        AchievementAward::factory()->create(['is_sensitive' => true, 'sensitivity_note' => 'A marshal was injured.']);

        $this->assertSame([$final->id], AchievementAward::query()->publishable()->pluck('id')->all());
    }

    public function test_a_confirmed_proposal_keeps_the_weekend_sensitive(): void
    {
        config(['achievements.sensitivity.propose_from' => '2024-01-01']);
        $this->weekend();
        $this->heavyCrash(medicalCar: true);
        $this->runEngine($this->race);

        MomentSensitivity::query()->sole()->confirm();
        $this->runEngine($this->race);

        $this->assertFalse(MomentSensitivity::query()->sole()->isProposal());
        $this->assertTrue(Sensitivity::forRound($this->race->round_id)['sensitive']);
        $this->assertNotContains('took_the_lead', $this->keys());
    }
}
