<?php

namespace App\Normalizers\OpenF1\Support;

use App\Models\OpenF1SessionDriver;
use App\Models\RaceSession;
use App\Normalizers\MissingDependencyException;

/**
 * Finds the race session and driver entries that per-session OpenF1
 * responses belong to.
 */
class OpenF1Resolver
{
    /**
     * @param  list<array<string, mixed>>  $payload  Rows of one session
     *
     * @throws MissingDependencyException when the session or its drivers are not normalized yet
     */
    public function sessionContext(array $payload): SessionContext
    {
        $session = $this->raceSession((int) $payload[0]['session_key']);

        $entryIds = OpenF1SessionDriver::query()
            ->where('race_session_id', $session->id)
            ->pluck('driver_entry_id', 'driver_number')
            ->all();

        if ($entryIds === []) {
            throw new MissingDependencyException("Drivers of OpenF1 session {$session->openf1_session_key} are not normalized yet.");
        }

        return new SessionContext($session, $entryIds);
    }

    /**
     * @throws MissingDependencyException when no race session is matched to the OpenF1 session
     */
    public function raceSession(int $sessionKey): RaceSession
    {
        return RaceSession::query()->with('round.season')->firstWhere('openf1_session_key', $sessionKey)
            ?? throw new MissingDependencyException("OpenF1 session {$sessionKey} is not matched to a race session yet.");
    }
}
