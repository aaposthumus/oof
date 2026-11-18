<?php

namespace App\Filament\Resources\DriverNicknames\Schemas;

use App\Enums\Breakthrough;
use App\Enums\NicknameStatus;
use App\Models\Driver;
use App\Models\DriverNickname;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DriverNicknameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('driver_id')
                    ->label('Driver')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Driver::query()
                        ->where(fn ($query) => $query->where('family_name', 'like', "%{$search}%")->orWhere('given_name', 'like', "%{$search}%"))
                        ->orderBy('family_name')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Driver $driver) => [$driver->id => "{$driver->given_name} {$driver->family_name}"])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($driver = Driver::find($value)) === null ? null : "{$driver->given_name} {$driver->family_name}")
                    ->live()
                    ->disabledOn('edit'),
                Select::make('evolves_from_id')
                    ->label('Next stage of')
                    ->options(fn (Get $get): array => DriverNickname::query()
                        ->where('driver_id', $get('driver_id'))
                        ->whereDoesntHave('nextStage')
                        ->pluck('nickname', 'id')
                        ->all())
                    ->helperText('Leave empty for the driver\'s first nickname. Pick one to plan its evolution: the new stage waits as planned until an evolution is approved and takes effect.')
                    ->visibleOn('create'),
                TextInput::make('nickname')
                    ->required()
                    ->maxLength(64)
                    ->helperText('CamelCase for several words (ChecoMate). Changing it is a correction: every award, subtitle and evolution shows the new text at once.'),
                TextInput::make('motto')
                    ->maxLength(255),
                Toggle::make('is_draft')
                    ->label('Draft')
                    ->default(true)
                    ->helperText('Not final yet.'),
                CheckboxList::make('evolution_trigger')
                    ->label('Breakthroughs that may propose this stage')
                    ->options(collect(Breakthrough::cases())->mapWithKeys(fn (Breakthrough $breakthrough) => [$breakthrough->value => $breakthrough->label()])->all())
                    ->helperText('For a planned stage. None ticked: any of them.')
                    ->visible(fn (?DriverNickname $record, Get $get): bool => $record?->status === NicknameStatus::Planned || filled($get('evolves_from_id'))),
                TextInput::make('note')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->formatStateUsing(fn (?DriverNickname $record): string => match (true) {
                        $record === null => '',
                        default => $record->status->label()
                            .($record->active_from ? ' from '.$record->active_from->toDateString() : '')
                            .($record->active_to ? ' until '.$record->active_to->toDateString() : ''),
                    })
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Changes only through an evolution.')
                    ->visibleOn('edit'),
            ]);
    }
}
