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
 */
class ThisWeekInOofHistory
{
    /**
     * The week (Monday to Sunday) around a day.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, awards: Collection<int, AchievementAward>, moments: Collection<int, HistoricMoment>}
     */
    public function weekOf(CarbonInterface $day): array
    {
        $from = CarbonImmutable::parse($day)->startOfWeek();
        $to = $from->addDays(6);

        return ['from' => $from, 'to' => $to, 'awards' => $this->awards($from, $to), 'moments' => $this->moments($from, $to)];
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
