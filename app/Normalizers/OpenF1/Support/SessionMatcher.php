<?php

namespace App\Normalizers\OpenF1\Support;

use App\Enums\OpenF1MatchStatus;
use App\Enums\SessionType;
use App\Models\OpenF1Meeting;
use App\Models\RaceSession;
use App\Normalizers\Jolpica\Support\CoreResolver;
use Carbon\CarbonImmutable;

/**
 * Matches an OpenF1 session of a matched meeting to the round's session of
 * the same type. Practice sessions are not in Jolpica and are created.
 */
class SessionMatcher
{
    /**
     * @param  array<string, string>  $sessionTypes  OpenF1 session names mapped to session type values
     */
    public function __construct(
        private CoreResolver $core,
        private array $sessionTypes,
        private int $toleranceHours,
    ) {}

    /**
     * @param  array<string, mixed>  $session  An OpenF1 session
     */
    public function match(array $session, OpenF1Meeting $meeting): SessionMatch
    {
        if ($session['is_cancelled'] ?? false) {
            return new SessionMatch(OpenF1MatchStatus::Cancelled);
        }

        $type = SessionType::tryFrom($this->sessionTypes[$session['session_name']] ?? '');

        if ($type === null) {
            return new SessionMatch(OpenF1MatchStatus::NotARound, note: "\"{$session['session_name']}\" is not a session type of ours.");
        }

        if ($meeting->round_id === null) {
            return new SessionMatch(
                $meeting->match_status === OpenF1MatchStatus::NotARound ? OpenF1MatchStatus::NotARound : OpenF1MatchStatus::Unmatched,
                note: 'Its meeting is not matched to a round.',
            );
        }

        $start = OpenF1Time::parse($session['date_start']);

        if ($type->isPractice()) {
            $raceSession = $this->core->raceSession($meeting->round, $type, $start->toDateString(), $start->format('H:i:s'));

            return new SessionMatch(OpenF1MatchStatus::Created, $raceSession);
        }

        $raceSession = RaceSession::query()->where('round_id', $meeting->round_id)->where('type', $type)->first();

        if ($raceSession === null) {
            return new SessionMatch(OpenF1MatchStatus::Unmatched, note: "Round has no {$type->value} session.");
        }

        if ($raceSession->scheduled_date !== null) {
            $scheduled = CarbonImmutable::parse($raceSession->scheduled_date->toDateString().' '.($raceSession->scheduled_time ?? '12:00:00'), 'UTC');

            if (abs($scheduled->diffInHours($start)) > $this->toleranceHours) {
                return new SessionMatch(OpenF1MatchStatus::Conflict, note: "Starts {$start->toDateTimeString()}, but our {$type->value} is scheduled for {$scheduled->toDateTimeString()}.");
            }
        }

        return new SessionMatch(OpenF1MatchStatus::Matched, $raceSession);
    }
}
