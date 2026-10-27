<?php

namespace App\Excavation\Excavators;

use App\Enums\SessionType;
use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\Excavator;
use App\Excavation\Support\CareerTimeline;
use App\Models\QualifyingResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The youngest and oldest winners, pole sitters and podium finishers: each
 * driver once, youngest at their first and oldest at their last. Pole is
 * qualifying position 1 where qualifying data exists, otherwise grid 1, but
 * never on a shared car, whose grid both drivers get. Records set in the
 * same race by one driver become one candidate.
 */
class AgeRecordExcavator implements Excavator
{
    private const LABELS = ['win' => 'winner', 'pole' => 'pole sitter', 'podium' => 'podium finisher'];

    public function __construct(private CareerTimeline $timeline) {}

    public function key(): string
    {
        return 'age_record';
    }

    public function version(): int
    {
        return 1;
    }

    public function dig(DigParameters $parameters): Collection
    {
        $top = (int) config('excavators.age_record.top');
        $rows = $this->timeline->rows()
            ->filter(fn (object $row) => $row->date_of_birth !== null)
            ->each(fn (object $row) => $row->age_days ??= (int) CarbonImmutable::parse($row->date_of_birth)->diffInDays(CarbonImmutable::parse($row->date)));

        $categories = [
            'win' => $rows->where('is_win', true),
            'pole' => $this->poles($rows),
            'podium' => $rows->where('is_podium', true),
        ];

        $records = collect();

        foreach ($categories as $category => $hits) {
            foreach (['youngest' => false, 'oldest' => true] as $direction => $descending) {
                $hits->groupBy('driver_id')
                    ->map(fn (Collection $driverHits) => $descending ? $driverHits->sortByDesc('age_days')->first() : $driverHits->sortBy('age_days')->first())
                    ->sortBy('age_days', descending: $descending)
                    ->take($top)
                    ->values()
                    ->each(fn (object $row, int $index) => $records->push([
                        'row' => $row,
                        'rank' => $index + 1,
                        'kind' => "{$direction} ".self::LABELS[$category],
                        'record' => "{$direction} ".self::LABELS[$category].' #'.($index + 1),
                        'score' => round(100 * ($top - $index) / $top, 2),
                    ]));
            }
        }

        return $records
            ->filter(fn (array $record) => $parameters->includes($record['row']->year))
            ->groupBy(fn (array $record) => $record['row']->round_id.':'.$record['row']->driver_id)
            ->map(function (Collection $driverRecords) {
                $row = $driverRecords->first()['row'];
                $best = $driverRecords->sortBy('rank')->first();

                return new Candidate(
                    roundId: $row->round_id,
                    driverId: $row->driver_id,
                    seasonTeamId: $row->season_team_id,
                    score: (float) $driverRecords->max('score'),
                    evidence: [
                        'records' => $driverRecords->sortBy('rank')->pluck('record')->implode(', '),
                        'age_days' => $row->age_days,
                        'age_years' => (int) CarbonImmutable::parse($row->date_of_birth)->diffInYears(CarbonImmutable::parse($row->date)),
                        'best_record' => $best['kind'],
                        'best_rank' => $best['rank'],
                        'all_time_record' => $best['rank'] === 1,
                    ],
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function poles(Collection $rows): Collection
    {
        $qualifyingPoles = QualifyingResult::query()
            ->toBase()
            ->join('driver_entries', 'driver_entries.id', '=', 'qualifying_results.driver_entry_id')
            ->join('race_sessions', 'race_sessions.id', '=', 'driver_entries.race_session_id')
            ->where('race_sessions.type', SessionType::Qualifying)
            ->where('qualifying_results.position', 1)
            ->get(['race_sessions.round_id', 'driver_entries.driver_id'])
            ->mapWithKeys(fn (object $pole) => [(int) $pole->round_id => (int) $pole->driver_id]);

        return $rows->filter(fn (object $row) => $qualifyingPoles->has($row->round_id)
            ? $qualifyingPoles[$row->round_id] === $row->driver_id
            : $row->grid === 1 && ! $row->is_shared_car)
            ->unique(fn (object $row) => $row->round_id.':'.$row->driver_id);
    }
}
