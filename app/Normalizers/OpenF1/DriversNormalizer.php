<?php

namespace App\Normalizers\OpenF1;

use App\Models\DriverEntry;
use App\Models\OpenF1SessionDriver;
use App\Normalizers\MissingDependencyException;
use App\Normalizers\Normalizer;
use App\Normalizers\OpenF1\Support\DriverMatcher;
use App\Normalizers\OpenF1\Support\OpenF1Resolver;
use App\Normalizers\OpenF1\Support\SessionEntryLinker;
use Illuminate\Support\Facades\Log;

/**
 * Records the cars of an OpenF1 session and matches each to a driver, team
 * and driver entry. Unmatched cars are kept with what OpenF1 says about them,
 * and the session's other OpenF1 rows are linked to the matched entries.
 */
class DriversNormalizer implements Normalizer
{
    public function __construct(
        private OpenF1Resolver $openF1,
        private DriverMatcher $matcher,
        private SessionEntryLinker $linker,
    ) {}

    public function endpoint(): string
    {
        return 'drivers';
    }

    public function normalize(array $payload): void
    {
        if ($payload === []) {
            return;
        }

        $session = $this->openF1->raceSession((int) $payload[0]['session_key']);

        if ($session->type->hasJolpicaEntries() && ! DriverEntry::where('race_session_id', $session->id)->exists()) {
            throw new MissingDependencyException("Jolpica entries of {$session->type->value} {$session->round->season->year}-{$session->round->round_number} are not normalized yet.");
        }

        $numbers = [];

        foreach ($payload as $row) {
            $match = $this->matcher->match($session, $row);
            $numbers[] = (int) $row['driver_number'];

            OpenF1SessionDriver::updateOrCreate(['race_session_id' => $session->id, 'driver_number' => $row['driver_number']], [
                'name_acronym' => $row['name_acronym'] ?? null,
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'full_name' => $row['full_name'] ?? null,
                'broadcast_name' => $row['broadcast_name'] ?? null,
                'team_name' => $row['team_name'] ?? null,
                'team_colour' => $row['team_colour'] ?? null,
                'headshot_reference' => self::headshotReference($row['headshot_url'] ?? null),
                'driver_id' => $match->driver?->id,
                'season_team_id' => $match->seasonTeam?->id,
                'driver_entry_id' => $match->entry?->id,
                'match_status' => $match->status,
                'match_note' => $match->note,
            ]);

            if ($match->status->needsAttention()) {
                Log::warning("OpenF1 car {$row['driver_number']} in session {$session->openf1_session_key}: {$match->note}");
            }
        }

        OpenF1SessionDriver::where('race_session_id', $session->id)->whereNotIn('driver_number', $numbers)->delete();

        $this->linker->relink($session);
    }

    /**
     * Formula 1's own driver reference in a headshot URL, e.g. "lannor01".
     */
    private static function headshotReference(?string $url): ?string
    {
        return $url !== null && preg_match('#/([a-z]{6}\d{2})\.png#', $url, $matches) === 1 ? $matches[1] : null;
    }
}
