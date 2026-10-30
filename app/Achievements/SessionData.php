<?php

namespace App\Achievements;

use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\IncidentThread;
use App\Achievements\Support\IncidentThreads;
use App\Enums\SessionType;
use App\Models\DriverEntry;
use App\Models\PitLaneVisit;
use App\Models\Position;
use App\Models\RaceControlMessage;
use App\Models\RaceSession;
use App\Models\SessionLap;
use App\RaceControl\MessageClassifier;
use App\RaceControl\MessageType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the detectors read about one session, loaded once and shared.
 */
class SessionData
{
    /**
     * @var Collection<int, ClassifiedMessage>|null
     */
    private ?Collection $messages = null;

    /**
     * @var list<IncidentThread>|null
     */
    private ?array $threads = null;

    /**
     * @var array<string, Collection<int, mixed>>
     */
    private array $loaded = [];

    /**
     * @param  Collection<int, DriverEntry>  $entries  Keyed by ID
     */
    public function __construct(
        public readonly RaceSession $session,
        public readonly Collection $entries,
        private readonly MessageClassifier $classifier,
    ) {}

    public static function load(RaceSession $session, MessageClassifier $classifier): self
    {
        $session->loadMissing(['round.season', 'openF1Session']);

        $entries = $session->driverEntries()
            ->with(['driver', 'seasonTeam', 'result', 'qualifyingResult'])
            ->get()
            ->keyBy('id');

        return new self($session, $entries, $classifier);
    }

    public function type(): SessionType
    {
        return $this->session->type;
    }

    public function year(): int
    {
        return $this->session->round->season->year;
    }

    public function entry(?int $entryId): ?DriverEntry
    {
        return $entryId === null ? null : $this->entries->get($entryId);
    }

    /**
     * Entries with a race or sprint result row.
     *
     * @return Collection<int, DriverEntry>
     */
    public function resultEntries(): Collection
    {
        return $this->entries->filter(fn (DriverEntry $entry) => $entry->result !== null);
    }

    /**
     * The session's race control messages in the order they were issued.
     *
     * @return Collection<int, ClassifiedMessage>
     */
    public function messages(): Collection
    {
        if ($this->messages !== null) {
            return $this->messages;
        }

        $phase = null;

        // A message without a qualifying phase falls in the phase of the last message that had one.
        return $this->messages = RaceControlMessage::query()
            ->where('race_session_id', $this->session->id)
            ->with('involvedEntries:id')
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get()
            ->map(function (RaceControlMessage $message) use (&$phase) {
                $phase = $message->qualifying_phase ?? $phase;

                return new ClassifiedMessage(
                    $message,
                    $this->classifier->classify($message->message, $message->flag),
                    $message->involvedEntries->pluck('id')->all(),
                    $phase,
                );
            });
    }

    /**
     * @return Collection<int, ClassifiedMessage>
     */
    public function messagesOfType(MessageType ...$types): Collection
    {
        return $this->messages()->filter(fn (ClassifiedMessage $message) => $message->is(...$types))->values();
    }

    /**
     * @return list<IncidentThread>
     */
    public function threads(): array
    {
        return $this->threads ??= IncidentThreads::build($this->messages());
    }

    /**
     * @return Collection<int, SessionLap>
     */
    public function laps(): Collection
    {
        return $this->loaded['laps'] ??= SessionLap::query()->where('race_session_id', $this->session->id)->orderBy('lap_number')->get();
    }

    /**
     * @return Collection<int, PitLaneVisit>
     */
    public function pitVisits(): Collection
    {
        return $this->loaded['pit'] ??= PitLaneVisit::query()->where('race_session_id', $this->session->id)->orderBy('entered_at')->get();
    }

    /**
     * @return Collection<int, Position>
     */
    public function positions(): Collection
    {
        return $this->loaded['positions'] ??= Position::query()->where('race_session_id', $this->session->id)->orderBy('recorded_at')->get();
    }

    /**
     * Entries of drivers who race this weekend, so practice-only drivers and
     * FP1 rookies are left out.
     *
     * @return Collection<int, DriverEntry>
     */
    public function raceDriverEntries(): Collection
    {
        $raceDrivers = $this->loaded['race_drivers'] ??= DriverEntry::query()
            ->whereHas('raceSession', fn ($query) => $query->where('round_id', $this->session->round_id)->whereIn('type', [SessionType::Race, SessionType::Sprint]))
            ->pluck('driver_id')
            ->unique()
            ->values();

        return $this->entries->filter(fn (DriverEntry $entry) => $raceDrivers->contains($entry->driver_id));
    }

    /**
     * Another session of the same round, if it exists.
     */
    public function roundSession(SessionType $type): ?self
    {
        if ($type === $this->type()) {
            return $this;
        }

        $session = RaceSession::query()->where('round_id', $this->session->round_id)->where('type', $type)->first();

        return $session === null ? null : self::load($session, $this->classifier);
    }

    /**
     * When the session ended: OpenF1's end time, or else its scheduled start plus three hours.
     */
    public function endsAt(): CarbonImmutable
    {
        if ($this->session->openF1Session?->date_end !== null) {
            return CarbonImmutable::parse($this->session->openF1Session->date_end);
        }

        $date = $this->session->scheduled_date ?? $this->session->round->date;
        $time = $this->session->scheduled_time ?? $this->session->round->time ?? '23:59:59';

        return CarbonImmutable::parse($date->format('Y-m-d').' '.$time, 'UTC')->addHours(3);
    }

    /**
     * Whether the stewards are done: the results are in and enough time has passed.
     */
    public function isFinal(CarbonImmutable $now): bool
    {
        $hasResults = match (true) {
            in_array($this->type(), [SessionType::Race, SessionType::Sprint], true) => $this->resultEntries()->isNotEmpty(),
            in_array($this->type(), [SessionType::Qualifying, SessionType::SprintQualifying], true) => $this->entries->contains(fn (DriverEntry $entry) => $entry->qualifyingResult !== null),
            default => true,
        };

        return $hasResults && $this->endsAt()->addHours((int) config('achievements.final_after_hours'))->lessThanOrEqualTo($now);
    }
}
