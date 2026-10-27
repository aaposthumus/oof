<?php

namespace App\Excavation\Support;

use Illuminate\Support\Collection;

/**
 * The winner and runner-up of one race, and the other classified cars. A
 * shared car counts once; disqualified drivers do not count.
 */
final readonly class RaceFinish
{
    /**
     * @param  Collection<int, object>  $rivals  Classified cars other than the winner's
     */
    public function __construct(
        public object $winner,
        public ?object $runnerUp,
        public Collection $rivals,
    ) {}

    /**
     * @param  Collection<int, object>  $race  CareerTimeline rows of one round
     */
    public static function of(Collection $race): ?self
    {
        $winner = $race->firstWhere('is_win', true);

        if ($winner === null) {
            return null;
        }

        $rivals = $race
            ->filter(fn (object $row) => $row->position !== null && $row->position > 1 && ! $row->is_disqualified)
            ->unique(fn (object $row) => $row->season_team_id.':'.$row->car_number)
            ->values();

        return new self($winner, $rivals->firstWhere('position', 2), $rivals);
    }

    /**
     * Milliseconds between winner and runner-up, when both times are known and they finished on the same lap.
     */
    public function gapMillis(): ?int
    {
        if ($this->runnerUp?->time_millis === null || $this->winner->time_millis === null || $this->runnerUp->laps_completed !== $this->winner->laps_completed) {
            return null;
        }

        return $this->runnerUp->time_millis - $this->winner->time_millis;
    }
}
