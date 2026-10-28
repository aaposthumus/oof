<?php

namespace App\Normalizers\OpenF1;

use App\Models\Circuit;
use App\Models\OpenF1Meeting;
use App\Models\OpenF1Session;
use App\Models\RaceSession;
use App\Models\Round;
use App\Normalizers\MissingDependencyException;
use App\Normalizers\Normalizer;
use App\Normalizers\OpenF1\Support\MeetingMatcher;
use App\Normalizers\OpenF1\Support\SessionMatcher;

/**
 * Matches a season's OpenF1 meetings to rounds and their sessions to race
 * sessions, and stores the OpenF1 keys next to ours. Every meeting and
 * session is recorded with how it matched, so nothing is dropped unseen.
 */
class SessionsNormalizer implements Normalizer
{
    public function __construct(
        private MeetingMatcher $meetings,
        private SessionMatcher $sessions,
    ) {}

    public function endpoint(): string
    {
        return 'sessions';
    }

    public function normalize(array $payload): void
    {
        foreach (collect($payload)->groupBy('meeting_key') as $meetingKey => $sessions) {
            $meeting = OpenF1Meeting::firstWhere('meeting_key', $meetingKey)
                ?? throw new MissingDependencyException("OpenF1 meeting {$meetingKey} is not normalized yet.");

            $this->matchMeeting($meeting, $sessions->firstWhere('session_name', 'Race'));

            foreach ($sessions as $session) {
                $this->matchSession($session, $meeting);
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $race
     */
    private function matchMeeting(OpenF1Meeting $meeting, ?array $race): void
    {
        $match = $this->meetings->match($meeting, $race);

        Round::where('openf1_meeting_key', $meeting->meeting_key)
            ->when($match->round !== null, fn ($rounds) => $rounds->whereKeyNot($match->round->id))
            ->update(['openf1_meeting_key' => null]);

        if ($match->round !== null) {
            $match->round->update(['openf1_meeting_key' => $meeting->meeting_key]);

            if ($match->round->circuit->openf1_circuit_key === null) {
                Circuit::where('openf1_circuit_key', $meeting->circuit_key)->update(['openf1_circuit_key' => null]);
                $match->round->circuit->update(['openf1_circuit_key' => $meeting->circuit_key]);
            }
        }

        $meeting->update(['round_id' => $match->round?->id, 'match_status' => $match->status, 'match_note' => $match->note]);
        $meeting->setRelation('round', $match->round);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function matchSession(array $session, OpenF1Meeting $meeting): void
    {
        $match = $this->sessions->match($session, $meeting);

        RaceSession::where('openf1_session_key', $session['session_key'])
            ->when($match->raceSession !== null, fn ($raceSessions) => $raceSessions->whereKeyNot($match->raceSession->id))
            ->update(['openf1_session_key' => null]);

        $match->raceSession?->update(['openf1_session_key' => $session['session_key']]);

        OpenF1Session::updateOrCreate(['session_key' => $session['session_key']], [
            'meeting_key' => $session['meeting_key'],
            'year' => $session['year'],
            'name' => $session['session_name'],
            'session_type' => $session['session_type'],
            'date_start' => $session['date_start'],
            'date_end' => $session['date_end'] ?? null,
            'is_cancelled' => $session['is_cancelled'] ?? false,
            'race_session_id' => $match->raceSession?->id,
            'match_status' => $match->status,
            'match_note' => $match->note,
        ]);
    }
}
