<?php

namespace App\Normalizers\OpenF1\Support;

use App\Enums\OpenF1MatchStatus;
use App\Models\OpenF1Meeting;
use App\Models\Round;
use Carbon\CarbonImmutable;

/**
 * Matches an OpenF1 meeting to one of our rounds by the start of its race
 * and its circuit. The Grand Prix name is never the key: the 2026 Bahrain
 * Grand Prix was held at Sepang, and OpenF1 and Jolpica name some rounds
 * differently (São Paulo and Brazilian Grand Prix).
 */
class MeetingMatcher
{
    /**
     * @param  array<int, string>  $circuits  OpenF1 circuit keys mapped to Jolpica circuit IDs
     */
    public function __construct(
        private array $circuits,
        private int $toleranceHours,
    ) {}

    /**
     * @param  array<string, mixed>|null  $race  The meeting's OpenF1 race session, if it has one
     */
    public function match(OpenF1Meeting $meeting, ?array $race): MeetingMatch
    {
        if ($meeting->is_cancelled) {
            return new MeetingMatch(OpenF1MatchStatus::Cancelled);
        }

        if ($race === null) {
            return new MeetingMatch(OpenF1MatchStatus::NotARound, note: 'No race session (testing).');
        }

        $raceStart = OpenF1Time::parse($race['date_start']);
        $candidates = $this->roundsNear($meeting->year, $raceStart);

        if ($candidates === []) {
            return new MeetingMatch(OpenF1MatchStatus::Unmatched, note: "No {$meeting->year} round within {$this->toleranceHours}h of the race start {$raceStart->toDateTimeString()}.");
        }

        if (count($candidates) > 1) {
            return new MeetingMatch(OpenF1MatchStatus::Conflict, note: 'More than one round near the race start: rounds '.implode(', ', array_map(fn (Round $round) => $round->round_number, $candidates)).'.');
        }

        $round = $candidates[0];
        $circuitId = $this->circuits[$meeting->circuit_key] ?? null;

        if ($circuitId === null) {
            return new MeetingMatch(OpenF1MatchStatus::Conflict, note: "Circuit key {$meeting->circuit_key} ({$meeting->circuit_short_name}) is not in openf1.circuits; round {$round->round_number} is at {$round->circuit->jolpica_circuit_id}.");
        }

        if ($circuitId !== $round->circuit->jolpica_circuit_id) {
            return new MeetingMatch(OpenF1MatchStatus::Conflict, note: "Held at {$circuitId}, but round {$round->round_number} is at {$round->circuit->jolpica_circuit_id}.");
        }

        $note = in_array($meeting->name, [$round->name, $round->jolpica_race_name], true) ? null : "OpenF1 calls round {$round->round_number} \"{$meeting->name}\".";

        return new MeetingMatch(OpenF1MatchStatus::Matched, $round, $note);
    }

    /**
     * Rounds whose race starts within the tolerance. A round without a time
     * counts from noon UTC.
     *
     * @return list<Round>
     */
    private function roundsNear(int $year, CarbonImmutable $raceStart): array
    {
        $rounds = Round::query()
            ->with('circuit')
            ->whereRelation('season', 'year', $year)
            ->whereBetween('date', [$raceStart->subDays(2)->toDateString(), $raceStart->addDays(2)->toDateString()])
            ->get();

        return $rounds->filter(function (Round $round) use ($raceStart) {
            $roundStart = CarbonImmutable::parse($round->date->toDateString().' '.($round->time ?? '12:00:00'), 'UTC');

            return abs($roundStart->diffInHours($raceStart)) <= $this->toleranceHours;
        })->values()->all();
    }
}
