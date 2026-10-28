<?php

namespace App\OpenF1;

use App\Enums\OpenF1MatchStatus;
use App\Models\OpenF1Meeting;
use App\Models\OpenF1Session;
use App\Models\OpenF1SessionDriver;
use App\Models\Round;
use Illuminate\Database\Eloquent\Builder;

/**
 * How OpenF1 meetings, sessions and cars matched our rounds, sessions and
 * driver entries, and which ones need a look.
 */
class MatchReport
{
    /**
     * Counts per match status, for every OpenF1 ledger.
     *
     * @return array<string, array<string, int>> Keyed by ledger, then by status value
     */
    public function counts(): array
    {
        $ledgers = [
            'meetings' => OpenF1Meeting::class,
            'sessions' => OpenF1Session::class,
            'session drivers' => OpenF1SessionDriver::class,
        ];

        return array_map(fn (string $model) => $model::query()
            ->toBase()
            ->selectRaw('match_status, count(*) as total')
            ->groupBy('match_status')
            ->orderBy('match_status')
            ->pluck('total', 'match_status')
            ->map(fn ($total) => (int) $total)
            ->all(), $ledgers);
    }

    /**
     * Rounds from the first OpenF1 season on that have been raced but have no OpenF1 meeting.
     */
    public function roundsWithoutMeeting(): int
    {
        return Round::query()
            ->whereRelation('season', 'year', '>=', config('openf1.first_season'))
            ->whereDate('date', '<=', now())
            ->whereNull('openf1_meeting_key')
            ->count();
    }

    /**
     * Unmatched and conflicting items, newest first.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}> Kind, status, what, note
     */
    public function needingAttention(int $limit = 20): array
    {
        $statuses = array_filter(OpenF1MatchStatus::cases(), fn (OpenF1MatchStatus $status) => $status->needsAttention());
        $attention = fn (Builder $query) => $query->whereIn('match_status', $statuses);
        $items = [];

        foreach (OpenF1Meeting::query()->tap($attention)->latest('date_start')->limit($limit)->get() as $meeting) {
            $items[] = ['meeting', $meeting->match_status->value, "{$meeting->year} {$meeting->name} ({$meeting->meeting_key})", (string) $meeting->match_note];
        }

        foreach (OpenF1Session::query()->tap($attention)->latest('date_start')->limit($limit)->get() as $session) {
            $items[] = ['session', $session->match_status->value, "{$session->year} {$session->name} ({$session->session_key})", (string) $session->match_note];
        }

        $drivers = OpenF1SessionDriver::query()->tap($attention)->with('raceSession.round.season')->latest('id')->limit($limit)->get();

        foreach ($drivers as $driver) {
            $round = $driver->raceSession->round;
            $items[] = [
                'car',
                $driver->match_status->value,
                "{$round->season->year}-{$round->round_number} {$driver->raceSession->type->value} #{$driver->driver_number} {$driver->name_acronym} {$driver->full_name} ({$driver->team_name})",
                (string) $driver->match_note,
            ];
        }

        return array_slice($items, 0, $limit);
    }
}
