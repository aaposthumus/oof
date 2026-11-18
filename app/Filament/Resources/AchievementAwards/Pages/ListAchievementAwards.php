<?php

namespace App\Filament\Resources\AchievementAwards\Pages;

use App\Achievements\WithheldOnSensitiveWeekend;
use App\Enums\AwardStatus;
use App\Filament\Resources\AchievementAwards\AchievementAwardResource;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ListAchievementAwards extends ListRecords
{
    protected static string $resource = AchievementAwardResource::class;

    /**
     * Award a manual achievement (Built Different) by hand.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('awardByHand')
                ->label('Award by hand')
                ->icon('heroicon-o-hand-raised')
                ->color('gray')
                ->modalDescription('Only once the driver is confirmed OK (for example the next day), never when anyone else was hurt, and never on a sensitive weekend. The award is final at once.')
                ->schema([
                    Select::make('achievement_id')
                        ->label('Achievement')
                        ->options(fn (): array => Achievement::query()->where('is_manual', true)->where('is_enabled', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->required(),
                    Select::make('race_session_id')
                        ->label('Session')
                        ->options(fn (): array => RaceSession::query()
                            ->with('round.season')
                            ->whereHas('driverEntries')
                            ->whereHas('round.season', fn (Builder $query) => $query->where('year', '>=', config('achievements.first_season')))
                            ->orderByDesc('id')
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn (RaceSession $session) => [$session->id => "{$session->round->season->year} R{$session->round->round_number} {$session->round->name} · {$session->type->label()}"])
                            ->all())
                        ->searchable()
                        ->live()
                        ->required(),
                    Select::make('driver_entry_id')
                        ->label('Car')
                        ->options(fn (Get $get): array => DriverEntry::query()
                            ->where('race_session_id', $get('race_session_id'))
                            ->with('driver')
                            ->get()
                            ->mapWithKeys(fn (DriverEntry $entry) => [$entry->id => "#{$entry->car_number} {$entry->driver->given_name} {$entry->driver->family_name}"])
                            ->all())
                        ->required(),
                    Textarea::make('note')
                        ->label('Note')
                        ->helperText('How it was confirmed that the driver is OK, and when.')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        AchievementAward::awardByHand(
                            Achievement::query()->findOrFail($data['achievement_id']),
                            DriverEntry::query()->findOrFail($data['driver_entry_id']),
                            auth()->user(),
                            $data['note'],
                        );
                    } catch (WithheldOnSensitiveWeekend|InvalidArgumentException $exception) {
                        Notification::make()->danger()->title('Not awarded')->body($exception->getMessage())->send();

                        throw new Halt;
                    }

                    Notification::make()->success()->title('Awarded')->send();
                }),
        ];
    }

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
