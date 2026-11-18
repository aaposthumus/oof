<?php

namespace Tests\Feature\Achievements;

use App\Achievements\WithheldOnSensitiveWeekend;
use App\Enums\AwardSource;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use App\Models\MomentSensitivity;
use App\Models\RaceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * Built Different: awarded by hand only, never by the engine, and never on a
 * sensitive weekend.
 */
class ManualAwardTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private RaceSession $race;

    private DriverEntry $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        $this->race = $this->raceSession();
        $this->car = $this->car($this->race, 44);
        $this->resultFor($this->car, ['position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => 'Accident', 'laps_completed' => 12]);
    }

    private function builtDifferent(): Achievement
    {
        return Achievement::firstWhere('key', 'built_different');
    }

    public function test_it_is_a_manual_glory_achievement_withheld_on_sensitive_weekends(): void
    {
        $achievement = $this->builtDifferent();

        $this->assertTrue($achievement->is_manual);
        $this->assertTrue($achievement->withheld_on_sensitive_weekends);
        $this->assertSame([], $achievement->data_sources);
    }

    public function test_the_engine_never_awards_it_and_keeps_one_given_by_hand(): void
    {
        $this->runEngine($this->race);
        $this->assertCount(0, $this->awards('built_different'));

        $award = AchievementAward::awardByHand($this->builtDifferent(), $this->car, User::factory()->create(), 'Checked by the medical centre, said he was fine the next morning.');

        $this->assertSame(AwardStatus::Final, $award->status);
        $this->assertSame(AwardSource::Manual, $award->source);
        $this->assertSame($this->car->driver_id, $award->driver_id);
        $this->assertNotNull($award->finalized_at);

        $this->runEngine($this->race);

        $this->assertSame([$award->id], $this->awards('built_different')->pluck('id')->all());
        $this->assertSame(AwardStatus::Final, $award->fresh()->status);
    }

    public function test_it_is_refused_on_a_sensitive_weekend_and_suppressed_if_one_is_added_later(): void
    {
        $award = AchievementAward::awardByHand($this->builtDifferent(), $this->car, User::factory()->create(), 'Fine.');

        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);
        $this->runEngine($this->race);
        $this->assertSame(AwardStatus::Suppressed, $award->fresh()->status);

        $other = $this->car($this->race, 1);
        $this->expectException(WithheldOnSensitiveWeekend::class);
        AchievementAward::awardByHand($this->builtDifferent(), $other, User::factory()->create(), 'Fine.');
    }

    public function test_only_a_manual_achievement_is_awarded_by_hand_and_only_once_per_car(): void
    {
        AchievementAward::awardByHand($this->builtDifferent(), $this->car, User::factory()->create(), 'Fine.');

        try {
            AchievementAward::awardByHand(Achievement::firstWhere('key', 'did_the_thing'), $this->car, User::factory()->create(), 'No.');
            $this->fail('A detected achievement was awarded by hand.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        AchievementAward::awardByHand($this->builtDifferent(), $this->car, User::factory()->create(), 'Again.');
    }
}
