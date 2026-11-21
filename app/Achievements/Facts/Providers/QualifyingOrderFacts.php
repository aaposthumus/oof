<?php

namespace App\Achievements\Facts\Providers;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\FactEvidence;
use App\Achievements\Facts\FactProvider;
use App\Achievements\Facts\GatesSessions;
use App\Achievements\Facts\KeepsLeftCars;
use App\Achievements\SessionData;
use App\Achievements\Support\Surprise;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use Illuminate\Support\Collection;

/**
 * The qualifying order, for pole achievements that reach back to 1950
 * (Phase 11b, D10): in qualifying, the qualifying result facts; in a Grand
 * Prix of a round without qualifying data (every round before 1994 and most
 * of 1996 to 2002), the starting grid, grid 1 being the pole. A race of a
 * round with qualifying data gives no facts, so a pole is found once.
 * Attributes: position, surprise.
 *
 * The pole follows the official record (decided by the owner on
 * 21 November 2026): it is earned by one driver before the race, so it
 * keeps the car its driver later left for another (KeepsLeftCars), and when
 * several drivers are on grid 1 (a shared car, or a grid error)
 * config/official_poles.php names the one credited with it; an unlisted
 * tie gives no pole. The other drivers on grid 1 get no qualifying order.
 */
class QualifyingOrderFacts implements FactProvider, GatesSessions, KeepsLeftCars
{
    public function __construct(private QualifyingResultFacts $qualifying) {}

    public function sources(): array
    {
        return [DataSource::JolpicaQualifying, DataSource::JolpicaResults];
    }

    /**
     * Qualifying in qualifying, the grid in a race: a session without its own rows gives no facts.
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
        return ['Qualifying where present (from 1994), otherwise the starting grid.'];
    }

    public function facts(SessionData $data): Collection
    {
        return match ($data->type()) {
            SessionType::Qualifying => $this->qualifying->facts($data),
            SessionType::Race => self::hasQualifyingData($data) ? collect() : $this->grid($data),
            default => collect(),
        };
    }

    public static function hasQualifyingData(SessionData $data): bool
    {
        return (bool) $data->roundSession(SessionType::Qualifying)?->entries->contains(fn (DriverEntry $entry) => $entry->qualifyingResult !== null);
    }

    /**
     * @return Collection<int, Fact>
     */
    private function grid(SessionData $data): Collection
    {
        $pole = self::gridPoleEntryId($data);
        $shared = $data->resultEntries()->filter(fn (DriverEntry $entry) => (int) $entry->result->grid === 1)->count() > 1;

        return $data->resultEntries()
            ->filter(fn (DriverEntry $entry) => (int) $entry->result->grid > 1 || $entry->id === $pole)
            ->map(fn (DriverEntry $entry) => new Fact(
                type: 'qualifying_order',
                entryId: $entry->id,
                instanceKey: 'qualifying',
                eventKey: "qualifying:{$entry->id}",
                attributes: [
                    'position' => (int) $entry->result->grid,
                    'surprise' => fn () => Surprise::of($entry, $data),
                ],
                evidence: FactEvidence::results([$entry], $entry->id === $pole && $shared
                    ? 'No qualifying data for the round: the starting grid stands in. Several drivers are on grid 1; the pole follows the official record.'
                    : 'No qualifying data for the round: the starting grid stands in.'),
            ))
            ->values();
    }

    /**
     * The one car credited with pole from the grid: the car on grid 1 of its
     * only driver there (the car they started in, even if they left it), or
     * with several drivers on grid 1 the listed driver's (config/official_poles.php),
     * else none. Plain withdrawals (D9) never hold it.
     */
    public static function gridPoleEntryId(SessionData $data): ?int
    {
        $withdrawn = $data->withdrawnEntryIds();
        $onPole = $data->resultEntries()
            ->filter(fn (DriverEntry $entry) => (int) $entry->result->grid === 1 && ! in_array($entry->id, $withdrawn, true))
            ->values();
        $drivers = $onPole->pluck('driver_id')->unique();

        $driverId = match (true) {
            $drivers->count() === 1 => $drivers->first(),
            $drivers->count() > 1 => self::officialPoleDriverId($data, $onPole),
            default => null,
        };

        $cars = $onPole->where('driver_id', $driverId);
        $leftCars = $data->leftCarEntryIds();

        return ($cars->first(fn (DriverEntry $entry) => ! in_array($entry->id, $leftCars, true)) ?? $cars->sortBy('id')->first())?->id;
    }

    /**
     * @param  Collection<int, DriverEntry>  $onPole
     */
    private static function officialPoleDriverId(SessionData $data, Collection $onPole): ?int
    {
        $round = $data->session->round;
        $listed = collect(config('official_poles.rounds'))
            ->first(fn (array $pole) => $pole['season'] === $data->year() && $pole['round'] === $round->round_number);

        return $listed === null ? null : $onPole->first(fn (DriverEntry $entry) => $entry->driver->jolpica_driver_id === $listed['driver'])?->driver_id;
    }
}
