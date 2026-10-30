<?php

namespace App\Filament\Resources\AchievementAwards\Pages;

use App\Enums\AwardStatus;
use App\Filament\Resources\AchievementAwards\AchievementAwardResource;
use App\Models\AchievementAward;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAchievementAwards extends ListRecords
{
    protected static string $resource = AchievementAwardResource::class;

    /**
     * One tab per status, Pending first and so the default, then All.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = AchievementAward::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tabs = collect(AwardStatus::cases())->mapWithKeys(fn (AwardStatus $status) => [
            $status->value => Tab::make($status->label())
                ->badge((int) ($counts[$status->value] ?? 0))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value)),
        ]);

        return [...$tabs->all(), 'all' => Tab::make('All')->badge((int) $counts->sum())];
    }

    /**
     * The status the active tab shows, or null on All.
     */
    public function activeStatus(): ?AwardStatus
    {
        return AwardStatus::tryFrom((string) $this->activeTab);
    }
}
