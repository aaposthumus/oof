<?php

namespace Tests\Feature\Normalizers\OpenF1;

use App\Enums\OpenF1MatchStatus;
use App\Enums\SessionType;
use App\Models\Circuit;
use App\Models\OpenF1Meeting;
use App\Models\OpenF1Session;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\Season;
use App\Normalizers\MissingDependencyException;
use App\Normalizers\OpenF1\MeetingsNormalizer;
use App\Normalizers\OpenF1\SessionsNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\NormalizesOpenF1Fixtures;
use Tests\TestCase;

class SessionsNormalizerTest extends TestCase
{
    use LazilyRefreshDatabase, NormalizesOpenF1Fixtures;

    public function test_matches_the_2026_bahrain_grand_prix_held_at_sepang_and_not_the_cancelled_one(): void
    {
        $this->matchSeason2026();

        $round = Round::query()->whereRelation('season', 'year', 2026)->where('round_number', 16)->first();

        $this->assertSame(1308, $round->openf1_meeting_key);
        $this->assertSame(12, $round->circuit->openf1_circuit_key);
        $this->assertSame(OpenF1MatchStatus::Cancelled, OpenF1Meeting::firstWhere('meeting_key', 1282)->match_status);
        $this->assertNull(OpenF1Meeting::firstWhere('meeting_key', 1282)->round_id);
    }

    public function test_matches_by_race_start_and_circuit_when_the_names_differ(): void
    {
        $this->matchSeason2026();

        $meeting = OpenF1Meeting::firstWhere('meeting_key', 1299);

        $this->assertSame(OpenF1MatchStatus::Matched, $meeting->match_status);
        $this->assertSame(20, $meeting->round->round_number);
        $this->assertSame('Brazilian Grand Prix', $meeting->round->name);
        $this->assertStringContainsString('São Paulo Grand Prix', $meeting->match_note);
    }

    public function test_records_testing_as_not_a_round(): void
    {
        $this->matchSeason2026();

        $this->assertSame(OpenF1MatchStatus::NotARound, OpenF1Meeting::firstWhere('meeting_key', 1304)->match_status);
        $this->assertSame(
            [OpenF1MatchStatus::NotARound],
            OpenF1Session::where('meeting_key', 1304)->pluck('match_status')->unique()->values()->all(),
        );
    }

    public function test_links_jolpica_sessions_and_creates_practice_sessions(): void
    {
        $this->matchSeason2026();

        $sessions = RaceSession::whereRelation('round', 'openf1_meeting_key', 1297)->get()->keyBy(fn (RaceSession $session) => $session->type->value);

        $this->assertSame(11396, $sessions['race']->openf1_session_key);
        $this->assertSame(11392, $sessions['qualifying']->openf1_session_key);
        $this->assertSame(11389, $sessions['practice_1']->openf1_session_key);
        $this->assertSame('2026-10-23', $sessions['practice_1']->scheduled_date->toDateString());
        $this->assertSame(OpenF1MatchStatus::Created, OpenF1Session::firstWhere('session_key', 11389)->match_status);
        $this->assertSame(OpenF1MatchStatus::Matched, OpenF1Session::firstWhere('session_key', 11396)->match_status);
    }

    public function test_rematching_is_idempotent(): void
    {
        $this->matchSeason2026();
        $this->normalizeOpenF1(SessionsNormalizer::class, 'sessions_2026');

        $this->assertSame(1, RaceSession::where('type', SessionType::Practice1)->whereRelation('round', 'openf1_meeting_key', 1297)->count());
        $this->assertSame(count($this->openF1Fixture('sessions_2026')), OpenF1Session::count());
    }

    public function test_tolerates_the_las_vegas_race_dated_a_day_early_by_jolpica(): void
    {
        $round = Round::factory()
            ->for(Season::factory()->state(['year' => 2024]))
            ->for(Circuit::factory()->state(['jolpica_circuit_id' => 'vegas']))
            ->create(['round_number' => 22, 'date' => '2024-11-23', 'time' => '06:00:00', 'name' => 'Las Vegas Grand Prix', 'jolpica_race_name' => 'Las Vegas Grand Prix']);
        RaceSession::factory()->for($round)->create(['scheduled_date' => '2024-11-23', 'scheduled_time' => '06:00:00']);

        $this->normalizeOpenF1(MeetingsNormalizer::class, 'meetings_2024_las_vegas');
        $this->normalizeOpenF1(SessionsNormalizer::class, 'sessions_2024_las_vegas');

        $this->assertNotNull($round->fresh()->openf1_meeting_key);
        $this->assertSame(9644, RaceSession::where('round_id', $round->id)->where('type', SessionType::Race)->value('openf1_session_key'));
    }

    public function test_reports_a_circuit_conflict_and_leaves_the_round_unlinked(): void
    {
        $round = Round::factory()
            ->for(Season::factory()->state(['year' => 2024]))
            ->for(Circuit::factory()->state(['jolpica_circuit_id' => 'bahrain']))
            ->create(['date' => '2024-11-23', 'time' => '06:00:00']);

        $this->normalizeOpenF1(MeetingsNormalizer::class, 'meetings_2024_las_vegas');
        $this->normalizeOpenF1(SessionsNormalizer::class, 'sessions_2024_las_vegas');

        $meeting = OpenF1Meeting::sole();

        $this->assertSame(OpenF1MatchStatus::Conflict, $meeting->match_status);
        $this->assertStringContainsString('Held at vegas', $meeting->match_note);
        $this->assertNull($round->fresh()->openf1_meeting_key);
        $this->assertSame(OpenF1MatchStatus::Unmatched, OpenF1Session::firstWhere('session_key', 9644)->match_status);
    }

    public function test_reports_a_meeting_without_a_round(): void
    {
        Season::factory()->create(['year' => 2024]);

        $this->normalizeOpenF1(MeetingsNormalizer::class, 'meetings_2024_las_vegas');
        $this->normalizeOpenF1(SessionsNormalizer::class, 'sessions_2024_las_vegas');

        $this->assertSame(OpenF1MatchStatus::Unmatched, OpenF1Meeting::sole()->match_status);
    }

    public function test_defers_sessions_until_their_meetings_are_normalized(): void
    {
        $this->expectException(MissingDependencyException::class);

        $this->normalizeOpenF1(SessionsNormalizer::class, 'sessions_2026');
    }
}
