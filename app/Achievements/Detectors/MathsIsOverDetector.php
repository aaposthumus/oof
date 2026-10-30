<?php

namespace App\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Achievements\Support\Evidence;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use App\Models\DriverStanding;
use App\Models\RaceSession;
use App\Models\Round;
use Illuminate\Support\Collection;

/**
 * Championship clinched in this race or sprint: the leader's margin is more
 * than the points still to be won, and was not before the session.
 */
class MathsIsOverDetector implements Detector
{
    public function key(): string
    {
        return 'maths_is_over';
    }

    public function detect(SessionData $data): Collection
    {
        $round = $data->session->round;
        $isSprint = $data->type() === SessionType::Sprint;
        $previous = $this->standings(Round::query()->where('season_id', $round->season_id)->where('round_number', '<', $round->round_number)->orderByDesc('round_number')->first());
        $sprintPoints = $this->sessionPoints($data->roundSession(SessionType::Sprint));

        if ($isSprint) {
            $before = $previous;
            $after = $this->add($previous, $sprintPoints);
        } else {
            $before = $this->add($previous, $sprintPoints);
            $after = $this->standings($round);
        }

        if ($after->isEmpty() || $data->resultEntries()->isEmpty()) {
            return collect();
        }

        $remainingAfter = $this->remaining($data, $round, $isSprint);
        $remainingBefore = $remainingAfter + $this->maxPoints($data->type(), $data->year());

        if ($this->clinched($before, $remainingBefore) !== null || ($champion = $this->clinched($after, $remainingAfter)) === null) {
            return collect();
        }

        $entry = $data->entries->first(fn (DriverEntry $entry) => $entry->driver_id === $champion);

        return $entry === null ? collect() : collect([new Detection(
            achievementKey: $this->key(),
            driverEntryId: $entry->id,
            instanceKey: 'title',
            eventKey: "title:{$entry->id}",
            evidence: Evidence::rows('jolpica', $after->sortDesc()->take(2)->map(fn (float $points, int $driverId) => [
                'driver_id' => $driverId,
                'points' => $points,
            ])->values()->all(), "At most {$remainingAfter} points were left to win."),
        )]);
    }

    /**
     * The leader's driver ID if no one can catch them any more.
     *
     * @param  Collection<int, float>  $points
     */
    private function clinched(Collection $points, int $remaining): ?int
    {
        $sorted = $points->sortDesc();

        if ($sorted->count() < 2) {
            return null;
        }

        return $sorted->first() - $sorted->values()[1] > $remaining ? $sorted->keys()->first() : null;
    }

    /**
     * @return Collection<int, float> Points by driver ID
     */
    private function standings(?Round $round): Collection
    {
        return $round === null ? collect() : DriverStanding::query()->where('round_id', $round->id)->pluck('points', 'driver_id')->map(fn ($points) => (float) $points);
    }

    /**
     * @return Collection<int, float> Points by driver ID
     */
    private function sessionPoints(?SessionData $sprint): Collection
    {
        return $sprint === null ? collect() : $sprint->resultEntries()->mapWithKeys(fn (DriverEntry $entry) => [$entry->driver_id => (float) $entry->result->points]);
    }

    /**
     * @param  Collection<int, float>  $standings
     * @param  Collection<int, float>  $points
     * @return Collection<int, float>
     */
    private function add(Collection $standings, Collection $points): Collection
    {
        return $standings->keys()->merge($points->keys())->unique()
            ->mapWithKeys(fn (int $driverId) => [$driverId => ($standings[$driverId] ?? 0.0) + ($points[$driverId] ?? 0.0)]);
    }

    /**
     * The most points still to win after this session.
     */
    private function remaining(SessionData $data, Round $round, bool $isSprint): int
    {
        $later = RaceSession::query()
            ->whereIn('type', [SessionType::Race, SessionType::Sprint])
            ->whereHas('round', fn ($query) => $query->where('season_id', $round->season_id)->where('round_number', '>', $round->round_number))
            ->get()
            ->when($isSprint, fn (Collection $sessions) => $sessions->push(new RaceSession(['type' => SessionType::Race])));

        return $later->sum(fn (RaceSession $session) => $this->maxPoints($session->type, $data->year()));
    }

    private function maxPoints(SessionType $type, int $year): int
    {
        $points = config('achievements.max_points.'.($type === SessionType::Sprint ? 'sprint' : 'race'));

        return $points[$year] ?? $points['default'];
    }
}
