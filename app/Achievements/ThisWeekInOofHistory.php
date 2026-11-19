<?php

namespace App\Achievements;

use App\Enums\AwardStatus;
use App\Models\AchievementAward;
use App\Models\HistoricMoment;
use App\Models\MomentSensitivity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * This week in OOF history: what happened in a calendar window in any year
 * (wrapping at New Year). The final awards of every shelf, each with its
 * sensitive flag, and the historic moments of the window, flagged when
 * their round is sensitive. A person picks from it; automatic publishing
 * reads AchievementAward::publishable() instead, which leaves out flagged
 * awards and OOF before 2023.
 *
 * A week without awards or historic moments in any year (16 to 22 February
 * and 14 to 27 December never had a championship race) falls back to the
 * nearest week that has either, the earlier one on a tie (decided by the
 * owner on 19 November 2026).
 */
class ThisWeekInOofHistory
{
    /**
     * How far the fallback looks, in weeks each way: half a year reaches any calendar week.
     */
    private const FALLBACK_WEEKS = 26;

    /**
     * The week (Monday to Sunday) around a day, or the nearest week with
     * something to show. from and to are the week shown; requested_from and
     * requested_to the week asked for.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, requested_from: CarbonImmutable, requested_to: CarbonImmutable, is_fallback: bool, awards: Collection<int, AchievementAward>, moments: Collection<int, HistoricMoment>}
     */
    public function weekOf(CarbonInterface $day): array
    {
        $requested = CarbonImmutable::parse($day)->startOfWeek();
        $requestedWeek = ['requested_from' => $requested, 'requested_to' => $requested->addDays(6)];
        $week = $this->week($requested);

        if ($this->hasSomething($week)) {
            return [...$week, ...$requestedWeek, 'is_fallback' => false];
        }

        for ($offset = 1; $offset <= self::FALLBACK_WEEKS; $offset++) {
            foreach ([-$offset, $offset] as $weeks) {
                $candidate = $this->week($requested->addWeeks($weeks));

                if ($this->hasSomething($candidate)) {
                    return [...$candidate, ...$requestedWeek, 'is_fallback' => true];
                }
            }
        }

        return [...$week, ...$requestedWeek, 'is_fallback' => false];
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, awards: Collection<int, AchievementAward>, moments: Collection<int, HistoricMoment>}
     */
    private function week(CarbonImmutable $from): array
    {
        $to = $from->addDays(6);

        return ['from' => $from, 'to' => $to, 'awards' => $this->awards($from, $to), 'moments' => $this->moments($from, $to)];
    }

    /**
     * @param  array{awards: Collection<int, AchievementAward>, moments: Collection<int, HistoricMoment>}  $week
     */
    private function hasSomething(array $week): bool
    {
        return $week['awards']->isNotEmpty() || $week['moments']->isNotEmpty();
    }

    /**
     * @return Collection<int, AchievementAward>
     */
    public function awards(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return AchievementAward::query()
            ->inCalendarWindow($from, $to)
            ->where('status', AwardStatus::Final)
            ->with(['achievement', 'driver', 'round.season'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Each with is_sensitive set: true when its round has a sensitivity that was not dismissed.
     *
     * @return Collection<int, HistoricMoment>
     */
    public function moments(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $start = (int) $from->format('md');
        $end = (int) $to->format('md');
        $monthDay = 'month(date) * 100 + day(date)';

        $moments = HistoricMoment::query()
            ->when(
                $start <= $end,
                fn ($query) => $query->whereRaw("{$monthDay} between ? and ?", [$start, $end]),
                fn ($query) => $query->where(fn ($query) => $query->whereRaw("{$monthDay} >= ?", [$start])->orWhereRaw("{$monthDay} <= ?", [$end])),
            )
            ->with('round.season')
            ->orderBy('date')
            ->get();

        $sensitive = MomentSensitivity::query()->whereIn('round_id', $moments->pluck('round_id'))->whereNull('dismissed_at')->pluck('round_id')->flip();

        return $moments->each(fn (HistoricMoment $moment) => $moment->setAttribute('is_sensitive', $sensitive->has($moment->round_id)));
    }
}
