<?php

namespace App\Achievements;

use App\Achievements\Facts\Requirements;
use App\Achievements\Rules\Cascade;
use App\Achievements\Rules\CrossCheck;
use App\Achievements\Rules\Fairness;
use App\Achievements\Rules\Finality;
use App\Achievements\Rules\Sensitivity;
use App\Achievements\Rules\Stacking;
use App\Achievements\Triggers\Trigger;
use App\Achievements\Triggers\TriggerFactory;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\AchievementVariant;
use App\Models\RaceSession;
use App\RaceControl\MessageClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs the catalogue's triggers on a session, applies the rules and
 * stores the awards. Re-running a session updates its awards in place,
 * never duplicates them, and never changes what a person decided.
 *
 * An achievement is only looked for in a session its data supports (see
 * Requirements).
 *
 * Order: propose sensitivities, detect, fairness, cross-check, cascade,
 * sensitivity, stacking, the late triggers, sensitivity again, then
 * finality.
 */
class AchievementEngine
{
    public function __construct(
        private TriggerFactory $triggers,
        private Requirements $requirements,
        private MessageClassifier $classifier,
        private Fairness $fairness,
        private CrossCheck $crossCheck,
        private Cascade $cascade,
        private Stacking $stacking,
        private Finality $finality,
        private Sensitivity $sensitivity,
    ) {}

    /**
     * @return array<string, Trigger> Keyed by achievement key, in run order
     */
    public function triggers(): array
    {
        return $this->triggers->all();
    }

    public function trigger(string $key): Trigger
    {
        return $this->triggers->for($key);
    }

    /**
     * The settled detections of a session, before they are stored.
     *
     * @return Collection<int, Detection>
     */
    public function detect(SessionData $data): Collection
    {
        $catalogue = $this->catalogue();
        $triggers = collect($this->triggers())
            ->filter(fn (Trigger $trigger, string $key) => $catalogue->has($key) && $catalogue[$key]->appliesTo($data->type()) && $this->requirements->allows($key, $data));

        $detections = $triggers
            ->reject(fn (Trigger $trigger) => $trigger->isLate())
            ->flatMap(fn (Trigger $trigger) => $trigger->detect($data, collect()))
            ->values();

        $detections = $this->fairness->apply($detections, $catalogue);
        $detections = $this->crossCheck->apply($detections, $catalogue, $data);
        $detections = $this->cascade->apply($detections, $catalogue);
        $detections = $this->sensitivity->apply($detections, $catalogue, $data);
        $detections = $this->stacking->apply($detections, $catalogue);

        foreach ($triggers->filter(fn (Trigger $trigger) => $trigger->isLate()) as $trigger) {
            $detections = $detections->concat($trigger->detect($data, $detections))->values();
        }

        return $this->sensitivity->apply($detections, $catalogue, $data);
    }

    /**
     * Detect and store a session's awards. A dry run rolls everything back.
     *
     * @return array<string, array{found: int, created: int, updated: int, removed: int, pending: int}> Per achievement key
     */
    public function run(RaceSession $session, bool $dryRun = false, ?CarbonImmutable $now = null): array
    {
        $data = SessionData::load($session, $this->classifier);
        $sessionIsFinal = $this->finality->sessionIsFinal($data, $now);

        DB::beginTransaction();

        try {
            $this->sensitivity->propose($data);
            $detections = $this->detect($data);
            $stats = $this->store($data, $detections, $sessionIsFinal);
            $session->update(['achievements_checked_at' => $now ?? now()]);
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $stats;
    }

    /**
     * @param  Collection<int, Detection>  $detections
     * @return array<string, array{found: int, created: int, updated: int, removed: int, pending: int}>
     */
    private function store(SessionData $data, Collection $detections, bool $sessionIsFinal): array
    {
        $catalogue = $this->catalogue();
        $variants = AchievementVariant::query()->get()->keyBy(fn (AchievementVariant $variant) => "{$variant->achievement_id}|{$variant->driver_id}");
        $existing = AchievementAward::query()
            ->where('context_key', "session:{$data->session->id}")
            ->get()
            ->keyBy(fn (AchievementAward $award) => "{$award->achievement_id}|{$award->instance_key}");
        $isHistoric = $data->session->round->date->lt(CarbonImmutable::parse(config('achievements.review_from')));
        $round = $this->sensitivity->of($data);
        $stats = [];
        $kept = [];

        foreach ($detections as $detection) {
            $achievement = $catalogue[$detection->achievementKey];
            $stat = &$stats[$achievement->key];
            $stat ??= ['found' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'pending' => 0];
            $stat['found']++;

            $driverId = $data->entry($detection->driverEntryId)?->driver_id;
            [$status, $reviewReason] = $this->finality->judge($detection, $data, $sessionIsFinal, $driverId, $achievement);
            $key = "{$achievement->id}|{$detection->awardInstanceKey()}";
            $award = $existing->get($key) ?? new AchievementAward([
                'race_session_id' => $data->session->id,
                'achievement_id' => $achievement->id,
                'instance_key' => $detection->awardInstanceKey(),
            ]);
            $kept[$key] = true;

            $award->fill([
                'event_key' => $detection->eventKey,
                'detail' => $detection->detail,
                'confidence' => $detection->confidence,
            ]);

            // MySQL stores JSON objects with their keys reordered and whole floats as integers, so compare them canonically.
            if (! $award->exists || self::canonical($award->evidence) !== self::canonical($detection->evidence)) {
                $award->evidence = $detection->evidence;
            }

            // A person's decision stands: the car they picked, the status and their note.
            if (! $award->isDecided()) {
                // In a historic session nobody reviews what would wait for review, unless it was handed back.
                $suppressAsHistoric = $isHistoric && $status === AwardStatus::Pending && $reviewReason !== null && $award->handed_back_at === null;

                $award->fill([
                    'driver_entry_id' => $detection->driverEntryId,
                    'driver_id' => $driverId,
                    'status' => $suppressAsHistoric ? AwardStatus::Suppressed : $status,
                    'review_reason' => $reviewReason,
                    'decided_at' => $suppressAsHistoric ? $award->decided_at ?? now() : null,
                    'decision_note' => $suppressAsHistoric ? AchievementAward::HISTORIC_NOTE : null,
                ]);
            }

            if ($round['sensitive']) {
                $award->fill(['is_sensitive' => true, 'sensitivity_note' => $round['note']]);
            }

            $variant = $award->driver_id === null ? null : $variants->get("{$achievement->id}|{$award->driver_id}");
            $award->variant_name = $variant?->name;
            $award->achievement_variant_id = $variant?->id;

            if (! $award->exists) {
                $stat['created']++;
            } elseif ($award->isDirty()) {
                $stat['updated']++;
            }

            $stat['pending'] += $award->status === AwardStatus::Pending ? 1 : 0;
            $award->save();
            unset($stat);
        }

        foreach ($existing as $key => $award) {
            if (isset($kept[$key])) {
                continue;
            }

            $achievement = $catalogue->firstWhere('id', $award->achievement_id);
            $blocked = $round['blocked_round'] || ($award->driver_id !== null && in_array($award->driver_id, $round['blocked_drivers'], true));
            $withheld = $achievement !== null && ! Sensitivity::allows($round, $achievement, $award->driver_id);

            // What the engine made itself goes; what a person decided is suppressed by the system, keeping their decision.
            $outcome = match (true) {
                ! $award->isDecided() => 'delete',
                ($blocked || $withheld) && $award->decision_note !== Sensitivity::SYSTEM_NOTE => 'suppress',
                default => null,
            };

            if ($outcome === 'delete') {
                $award->delete();
            } elseif ($outcome === 'suppress') {
                $award->suppressBySystem($round['note']);
            }

            if ($outcome !== null) {
                $achievementKey = $achievement?->key ?? (string) $award->achievement_id;
                $stats[$achievementKey] ??= ['found' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'pending' => 0];
                $stats[$achievementKey]['removed']++;
            }
        }

        // Whatever stays of a sensitive round is flagged, decided or not; the flag never clears.
        if ($round['sensitive']) {
            AchievementAward::query()
                ->where('context_key', "session:{$data->session->id}")
                ->where('is_sensitive', false)
                ->update(['is_sensitive' => true, 'sensitivity_note' => $round['note'], 'updated_at' => now()]);
        }

        ksort($stats);

        return $stats;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => match (true) {
            is_array($item) => self::canonical($item),
            is_float($item) && floor($item) === $item => (int) $item,
            default => $item,
        }, $value);
    }

    /**
     * @return Collection<string, Achievement> Keyed by key
     */
    private function catalogue(): Collection
    {
        return Achievement::query()->get()->keyBy('key');
    }
}
