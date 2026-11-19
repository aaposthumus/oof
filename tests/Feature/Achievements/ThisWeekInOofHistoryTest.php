<?php

namespace Tests\Feature\Achievements;

use App\Achievements\ThisWeekInOofHistory;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\HistoricMoment;
use App\Models\MomentSensitivity;
use App\Models\Round;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class ThisWeekInOofHistoryTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private function award(string $date, array $attributes = []): AchievementAward
    {
        $day = CarbonImmutable::parse($date);
        $round = Round::query()->where('date', $date)->first() ?? $this->round($day->year, $day->day, ['date' => $date]);

        return AchievementAward::factory()->create([
            'achievement_id' => Achievement::factory()->create()->id,
            'race_session_id' => ($round->raceSessions()->where('type', SessionType::Race)->first() ?? $this->raceSession(SessionType::Race, $round))->id,
            ...$attributes,
        ]);
    }

    public function test_a_week_holds_the_final_awards_of_every_year_with_the_sensitive_flag(): void
    {
        $classic = $this->award('1955-05-22', ['is_sensitive' => true]);
        $recent = $this->award('2024-05-19');
        $this->award('2024-05-19', ['status' => AwardStatus::Suppressed]);
        $this->award('2024-05-26');

        $week = app(ThisWeekInOofHistory::class)->weekOf(CarbonImmutable::parse('2026-05-20'));

        $this->assertSame('2026-05-18', $week['from']->toDateString());
        $this->assertSame([$classic->id, $recent->id], $week['awards']->pluck('id')->all());
        $this->assertTrue($week['awards']->first()->is_sensitive);
    }

    public function test_a_week_over_new_year_wraps(): void
    {
        $december = $this->award('1967-12-30');
        $january = $this->award('1968-01-01');

        $awards = app(ThisWeekInOofHistory::class)->awards(CarbonImmutable::parse('2026-12-28'), CarbonImmutable::parse('2027-01-03'));

        $this->assertSame([$december->id, $january->id], $awards->pluck('id')->all());
    }

    public function test_historic_moments_of_the_week_are_flagged_when_their_round_is_sensitive(): void
    {
        $sensitiveRound = Round::factory()->create();
        MomentSensitivity::factory()->create(['round_id' => $sensitiveRound->id]);
        $flagged = HistoricMoment::factory()->create(['round_id' => $sensitiveRound->id, 'date' => '1961-09-10']);
        $plain = HistoricMoment::factory()->create(['date' => '1999-09-12']);
        HistoricMoment::factory()->create(['date' => '1999-09-14']);

        $moments = app(ThisWeekInOofHistory::class)->moments(CarbonImmutable::parse('2026-09-07'), CarbonImmutable::parse('2026-09-13'));

        $this->assertSame([$flagged->id, $plain->id], $moments->pluck('id')->all());
        $this->assertSame([true, false], $moments->pluck('is_sensitive')->all());
    }

    public function test_a_week_without_anything_falls_back_to_the_nearest_week_with_awards(): void
    {
        $this->award('1985-02-07');
        $nearer = $this->award('1990-03-01');

        $week = app(ThisWeekInOofHistory::class)->weekOf(CarbonImmutable::parse('2026-02-18'));

        $this->assertTrue($week['is_fallback']);
        $this->assertSame('2026-02-16', $week['requested_from']->toDateString());
        $this->assertSame('2026-02-23', $week['from']->toDateString());
        $this->assertSame([$nearer->id], $week['awards']->pluck('id')->all());
    }

    public function test_on_a_tie_the_earlier_week_is_shown(): void
    {
        $earlier = $this->award('1985-02-12');
        $this->award('1990-02-24');

        $week = app(ThisWeekInOofHistory::class)->weekOf(CarbonImmutable::parse('2026-02-18'));

        $this->assertSame('2026-02-09', $week['from']->toDateString());
        $this->assertSame([$earlier->id], $week['awards']->pluck('id')->all());
    }

    public function test_a_week_with_only_a_historic_moment_does_not_fall_back(): void
    {
        $this->award('1990-02-24');
        $moment = HistoricMoment::factory()->create(['date' => '1971-02-17']);

        $week = app(ThisWeekInOofHistory::class)->weekOf(CarbonImmutable::parse('2026-02-18'));

        $this->assertFalse($week['is_fallback']);
        $this->assertCount(0, $week['awards']);
        $this->assertSame([$moment->id], $week['moments']->pluck('id')->all());
    }

    public function test_the_command_says_when_it_falls_back(): void
    {
        $this->award('1990-03-01');

        $this->artisan('oof:history', ['date' => '2026-02-18'])
            ->expectsOutputToContain('Nothing ever happened 16 February to 22 February: showing the nearest week instead.')
            ->expectsOutputToContain('23 February to 1 March: 1 awards in 1 seasons')
            ->assertSuccessful();
    }

    public function test_the_command_shows_a_week(): void
    {
        $this->award('1955-05-22', ['is_sensitive' => true]);

        $this->artisan('oof:history', ['date' => '2026-05-20'])
            ->expectsOutputToContain('18 May to 24 May: 1 awards in 1 seasons (1 flagged sensitive), 0 historic moments.')
            ->assertSuccessful();
    }
}
