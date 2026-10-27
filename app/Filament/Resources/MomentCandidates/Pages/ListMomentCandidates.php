<?php

namespace App\Filament\Resources\MomentCandidates\Pages;

use App\Enums\MomentCandidateStatus;
use App\Filament\Resources\MomentCandidates\MomentCandidateResource;
use App\Models\MomentCandidate;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMomentCandidates extends ListRecords
{
    protected static string $resource = MomentCandidateResource::class;

    /**
     * One tab per review status, New first and so the default, then All.
     * Each shows how many candidates have that status.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = MomentCandidate::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tabs = collect(MomentCandidateStatus::cases())->mapWithKeys(fn (MomentCandidateStatus $status) => [
            $status->value => Tab::make(ucfirst($status->value))
                ->badge((int) ($counts[$status->value] ?? 0))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value)),
        ]);

        return [...$tabs->all(), 'all' => Tab::make('All')->badge((int) $counts->sum())];
    }

    /**
     * The status the active tab shows, or null on All.
     */
    public function activeStatus(): ?MomentCandidateStatus
    {
        return MomentCandidateStatus::tryFrom((string) $this->activeTab);
    }
}
