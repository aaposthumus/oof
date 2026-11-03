<?php

namespace App\Achievements\Rules;

use App\Achievements\SessionData;
use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\Finish;
use App\Models\DriverEntry;
use App\Models\SessionLap;
use App\RaceControl\MessageType;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use WeakMap;

/**
 * Whether a driver retired from a race or sprint in a heavy crash, judged
 * from race control around the moment the car stopped: a red flag, the
 * medical car, or the car stopped for a long time. Their awards for the
 * session wait for a person, so nobody is joked about before it is known
 * the driver is fine.
 *
 * The moment the car stopped is a message naming the car as stopped, off
 * track or in an incident, or else the end of its last lap.
 */
class HeavyCrash
{
    /**
     * @var WeakMap<SessionData, Collection<int, int>>|null
     */
    private ?WeakMap $cache = null;

    public function involves(SessionData $data, ?int $entryId): bool
    {
        if ($entryId === null) {
            return false;
        }

        $this->cache ??= new WeakMap;

        return ($this->cache[$data] ??= $this->entryIds($data))->contains($entryId);
    }

    /**
     * IDs of the entries that retired in a heavy crash.
     *
     * @return Collection<int, int>
     */
    private function entryIds(SessionData $data): Collection
    {
        return $data->resultEntries()
            ->filter(fn (DriverEntry $entry) => Finish::retired($entry->result) && $this->crashed($entry, $data))
            ->pluck('id')
            ->values();
    }

    private function crashed(DriverEntry $entry, SessionData $data): bool
    {
        $window = (int) config('achievements.cause_window_seconds');
        $own = $data->messages()->filter(fn (ClassifiedMessage $message) => $message->soleEntryId() === $entry->id);
        $stops = $own->filter(fn (ClassifiedMessage $message) => $message->is(MessageType::CarStopped, MessageType::OffTrack, MessageType::IncidentNoted));
        $moments = $stops->map(fn (ClassifiedMessage $message) => $message->message->issued_at)
            ->push($this->lastLapEnd($entry, $data))
            ->filter()
            ->values();

        $redFlag = $data->messagesOfType(MessageType::RedFlag)
            ->contains(fn (ClassifiedMessage $flag) => $moments->contains(fn (CarbonInterface $moment) => $flag->message->issued_at->betweenIncluded($moment, $moment->copy()->addSeconds($window))));

        $medicalCar = $data->messagesOfType(MessageType::MedicalCar)
            ->contains(fn (ClassifiedMessage $car) => in_array($entry->id, $car->entryIds, true)
                || $moments->contains(fn (CarbonInterface $moment) => $car->message->issued_at->betweenIncluded($moment->copy()->subSeconds($window), $moment->copy()->addSeconds(2 * $window))));

        return $redFlag || $medicalCar || $this->stoppedForLong($own, $data);
    }

    /**
     * When the car's last lap ended, from OpenF1's laps; a lap without a time ends where it started.
     */
    private function lastLapEnd(DriverEntry $entry, SessionData $data): ?CarbonInterface
    {
        /** @var SessionLap|null $lap */
        $lap = $data->laps()->where('driver_entry_id', $entry->id)->sortBy('lap_number')->last();

        return $lap?->started_at?->copy()->addMilliseconds($lap->duration_millis ?? 0);
    }

    /**
     * Whether the car sat stopped from its first stopped message until the
     * sector was cleared (or, without one, the session's last message) for
     * at least the configured time.
     *
     * @param  Collection<int, ClassifiedMessage>  $own
     */
    private function stoppedForLong(Collection $own, SessionData $data): bool
    {
        $stopped = $own->first(fn (ClassifiedMessage $message) => $message->is(MessageType::CarStopped));

        if ($stopped === null) {
            return false;
        }

        $from = $stopped->message->issued_at;
        $cleared = $data->messagesOfType(MessageType::ClearFlag)
            ->first(fn (ClassifiedMessage $message) => $message->message->issued_at->gt($from)
                && ($stopped->message->sector === null || $message->message->sector === $stopped->message->sector));
        $until = $cleared?->message->issued_at ?? $data->messages()->last()->message->issued_at;

        return $from->diffInSeconds($until) >= (int) config('achievements.heavy_crash_stopped_seconds');
    }
}
