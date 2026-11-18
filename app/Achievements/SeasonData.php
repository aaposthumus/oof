<?php

namespace App\Achievements;

use App\Enums\AwardScope;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use App\Models\OofscoreStanding;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\Season;
use App\RaceControl\MessageClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the season triggers read about one season, loaded once and
 * shared: its sessions, its final awards and its OOFscore standings.
 */
class SeasonData
{
    /**
     * @var array<int, SessionData>
     */
    private array $sessions = [];

    /**
     * @var Collection<int, AchievementAward>|null
     */
    private ?Collection $finalAwards = null;

    /**
     * @var Collection<int, DriverEntry>|null
     */
    private ?Collection $raceEntries = null;

    public function __construct(
        public readonly Season $season,
        private readonly MessageClassifier $classifier,
    ) {}

    public function year(): int
    {
        return $this->season->year;
    }

    public function finalRound(): ?Round
    {
        return Round::query()->where('season_id', $this->season->id)->orderByDesc('round_number')->first();
    }

    /**
     * The final round's race, which decides the season.
     */
    public function finalRace(): ?RaceSession
    {
        return $this->finalRound()?->raceSessions()->where('type', SessionType::Race)->first();
    }

    /**
     * Whether the season is over: its final race has been run by the engine and is final.
     */
    public function isFinal(CarbonImmutable $now): bool
    {
        $race = $this->finalRace();

        return $race !== null && $race->achievements_checked_at !== null && $this->session($race)->isFinal($now);
    }

    /**
     * The season's sessions of these types that were held, in calendar order.
     *
     * @param  list<SessionType>  $types
     * @return Collection<int, SessionData>
     */
    public function sessions(array $types): Collection
    {
        return RaceSession::query()
            ->with('round')
            ->whereIn('type', $types)
            ->whereHas('round', fn ($query) => $query->where('season_id', $this->season->id)->where('date', '<=', now()->toDateString()))
            ->get()
            ->sortBy(fn (RaceSession $session) => sprintf('%03d-%d', $session->round->round_number, $session->type->weekendOrder()))
            ->map(fn (RaceSession $session) => $this->session($session))
            ->values();
    }

    private function session(RaceSession $session): SessionData
    {
        return $this->sessions[$session->id] ??= SessionData::load($session, $this->classifier);
    }

    /**
     * The season's final awards of sessions and weekends, with their achievement and driver.
     *
     * @return Collection<int, AchievementAward>
     */
    public function finalAwards(): Collection
    {
        return $this->finalAwards ??= AchievementAward::query()
            ->with(['achievement', 'driver'])
            ->where('season_id', $this->season->id)
            ->where('scope', '!=', AwardScope::Season)
            ->where('status', AwardStatus::Final)
            ->orderBy('id')
            ->get();
    }

    /**
     * The OOFscore standings after the last round that has them, best first.
     *
     * @return Collection<int, OofscoreStanding>
     */
    public function finalStandings(): Collection
    {
        $lastRound = OofscoreStanding::query()
            ->join('rounds', 'rounds.id', '=', 'oofscore_standings.round_id')
            ->where('oofscore_standings.season_id', $this->season->id)
            ->orderByDesc('rounds.round_number')
            ->value('oofscore_standings.round_id');

        return OofscoreStanding::query()
            ->with('driver')
            ->where('round_id', $lastRound)
            ->orderBy('position')
            ->orderBy('driver_id')
            ->get();
    }

    /**
     * The driver's entry in the last race or sprint they drove this season,
     * which gives a season award its car and team.
     */
    public function entryOfDriver(int $driverId): ?DriverEntry
    {
        return $this->raceEntries()->last(fn (DriverEntry $entry) => $entry->driver_id === $driverId);
    }

    public function entry(?int $entryId): ?DriverEntry
    {
        return $entryId === null ? null : $this->raceEntries()->firstWhere('id', $entryId) ?? DriverEntry::query()->with('driver')->find($entryId);
    }

    /**
     * The season's race and sprint entries in calendar order.
     *
     * @return Collection<int, DriverEntry>
     */
    private function raceEntries(): Collection
    {
        return $this->raceEntries ??= DriverEntry::query()
            ->with(['driver', 'raceSession.round'])
            ->whereHas('raceSession', fn ($query) => $query
                ->whereIn('type', [SessionType::Race, SessionType::Sprint])
                ->whereHas('round', fn ($query) => $query->where('season_id', $this->season->id)))
            ->get()
            ->sortBy(fn (DriverEntry $entry) => sprintf('%03d-%d-%010d', $entry->raceSession->round->round_number, $entry->raceSession->type->weekendOrder(), $entry->id))
            ->values();
    }
}
