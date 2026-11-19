<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\Facts\GatesSessions;
use App\Achievements\Facts\ReadsEarlierRaces;
use App\Achievements\SessionData;
use App\Achievements\Support\Finish;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A driver's career first, read against every earlier Grand Prix since 1950.
 * In a Grand Prix: the first win, else the first podium, else the first
 * points (one fact per car, the most specific first). In Grand Prix
 * qualifying: the first pole, where an earlier pole is P1 in qualifying or,
 * in a round without a qualifying classification, grid 1. Sprints and sprint
 * qualifying do not count, as for the nickname breakthroughs.
 *
 * Attributes: first (win, podium, points or pole), label ("First win").
 */
class CareerFirstFacts implements FactProvider, GatesSessions, ReadsEarlierRaces
{
    private const LABELS = ['win' => 'First win', 'podium' => 'First podium', 'points' => 'First points', 'pole' => 'First pole'];

    public function sources(): array
    {
        return [DataSource::JolpicaResults, DataSource::JolpicaQualifying];
    }

    /**
     * Results in a Grand Prix, qualifying in qualifying: a session without its
     * own rows gives no facts.
     */
    public function sessionSources(): array
    {
        return [];
    }

    public function availableFrom(): int
    {
        return 1950;
    }

    public function notes(): array
    {
        return [
            'Grands Prix and their qualifying only; sprints do not count.',
            'An earlier pole is P1 in qualifying where present, otherwise grid 1.',
        ];
    }

    public function facts(SessionData $data): Collection
    {
        return match ($data->type()) {
            SessionType::Race => $this->raceFirsts($data)->concat($this->gridPoleFirsts($data))->values(),
            SessionType::Qualifying => $this->poleFirsts($data),
            default => collect(),
        };
    }

    /**
     * In a Grand Prix of a round without qualifying data, grid 1 is the pole
     * (Phase 11b, D10). A car with a first win the same day gets one award
     * with both ("×2 · First win · First pole").
     *
     * @return Collection<int, Fact>
     */
    private function gridPoleFirsts(SessionData $data): Collection
    {
        if (QualifyingOrderFacts::hasQualifyingData($data)) {
            return collect();
        }

        return $data->resultEntries()
            ->filter(fn (DriverEntry $entry) => $entry->result->grid === 1 && ! $this->hadPoleBefore($data, $entry->driver_id))
            ->map(fn (DriverEntry $entry) => $this->fact($entry, 'pole', instanceKey: 'first_pole'))
            ->values();
    }

    /**
     * @return Collection<int, Fact>
     */
    private function raceFirsts(SessionData $data): Collection
    {
        $candidates = $data->resultEntries()
            ->filter(fn (DriverEntry $entry) => Finish::isClassifiedFinisher($entry->result) && ! Finish::isDisqualified($entry->result) && ($entry->result->position <= 3 || (float) $entry->result->points > 0));

        if ($candidates->isEmpty()) {
            return collect();
        }

        $earlier = $this->earlierRaceResults($data, $candidates->pluck('driver_id')->all());

        return $candidates
            ->map(function (DriverEntry $entry) use ($earlier) {
                $before = $earlier->get($entry->driver_id);
                $first = match (true) {
                    $entry->result->position === 1 && ! ($before?->won ?? false) => 'win',
                    $entry->result->position <= 3 && ! ($before?->podium ?? false) => 'podium',
                    (float) $entry->result->points > 0 && ! ($before?->scored ?? false) => 'points',
                    default => null,
                };

                return $first === null ? null : $this->fact($entry, $first);
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Fact>
     */
    private function poleFirsts(SessionData $data): Collection
    {
        $pole = $data->entries->first(fn (DriverEntry $entry) => $entry->qualifyingResult?->position === 1);

        if ($pole === null || $this->hadPoleBefore($data, $pole->driver_id)) {
            return collect();
        }

        return collect([$this->fact($pole, 'pole')]);
    }

    private function fact(DriverEntry $entry, string $first, string $instanceKey = 'first'): Fact
    {
        return new Fact(
            type: 'career_first',
            entryId: $entry->id,
            instanceKey: $instanceKey,
            eventKey: "{$instanceKey}:{$entry->id}",
            attributes: ['first' => $first, 'label' => self::LABELS[$first]],
            evidence: FactEvidence::results([$entry], self::LABELS[$first].' in a Grand Prix career.'),
        );
    }

    /**
     * Per driver: whether they won, made the podium or scored in a Grand Prix before this round.
     *
     * @param  list<int>  $driverIds
     * @return Collection<int, object{won: bool, podium: bool, scored: bool}>
     */
    private function earlierRaceResults(SessionData $data, array $driverIds): Collection
    {
        return $this->earlierRounds($data, SessionType::Race)
            ->join('results', 'results.driver_entry_id', '=', 'driver_entries.id')
            ->whereIn('driver_entries.driver_id', $driverIds)
            ->groupBy('driver_entries.driver_id')
            ->selectRaw("driver_entries.driver_id, max(results.position_text = '1') as won, max(results.position_text in ('1', '2', '3')) as podium, max(results.points > 0) as scored")
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->driver_id => (object) ['won' => (bool) $row->won, 'podium' => (bool) $row->podium, 'scored' => (bool) $row->scored]]);
    }

    private function hadPoleBefore(SessionData $data, int $driverId): bool
    {
        $qualifyingPole = $this->earlierRounds($data, SessionType::Qualifying)
            ->join('qualifying_results', 'qualifying_results.driver_entry_id', '=', 'driver_entries.id')
            ->where('driver_entries.driver_id', $driverId)
            ->where('qualifying_results.position', 1)
            ->exists();

        if ($qualifyingPole) {
            return true;
        }

        return $this->earlierRounds($data, SessionType::Race)
            ->join('results', 'results.driver_entry_id', '=', 'driver_entries.id')
            ->where('driver_entries.driver_id', $driverId)
            ->where('results.grid', 1)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('race_sessions as qualifying')
                ->join('driver_entries as qualifying_entries', 'qualifying_entries.race_session_id', '=', 'qualifying.id')
                ->join('qualifying_results as classified', 'classified.driver_entry_id', '=', 'qualifying_entries.id')
                ->whereColumn('qualifying.round_id', 'rounds.id')
                ->where('qualifying.type', SessionType::Qualifying->value))
            ->exists();
    }

    /**
     * Driver entries of this session type in the rounds before this one.
     */
    private function earlierRounds(SessionData $data, SessionType $type): Builder
    {
        return DB::table('driver_entries')
            ->join('race_sessions', 'race_sessions.id', '=', 'driver_entries.race_session_id')
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->where('race_sessions.type', $type->value)
            ->where('rounds.date', '<', $data->session->round->date->toDateString());
    }
}
