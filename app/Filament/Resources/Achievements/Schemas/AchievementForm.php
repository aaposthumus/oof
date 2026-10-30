<?php

namespace App\Filament\Resources\Achievements\Schemas;

use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\SessionType;
use App\Models\Achievement;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AchievementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('key')
                    ->disabled()
                    ->helperText('Ties the achievement to its detector.'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('shelf')
                    ->options(collect(AchievementShelf::cases())->mapWithKeys(fn (AchievementShelf $shelf) => [$shelf->value => $shelf->label()])->all())
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::defaultPoints($get, $set))
                    ->required(),
                Select::make('section')
                    ->options(collect(AchievementSection::cases())->mapWithKeys(fn (AchievementSection $section) => [$section->value => $section->label()])->all())
                    ->helperText('Offence and penalty achievements need a message naming exactly one car.')
                    ->required(),
                Select::make('tier')
                    ->options(collect(AchievementTier::cases())->mapWithKeys(fn (AchievementTier $tier) => [$tier->value => "{$tier->label()} ({$tier->points()} points)"])->all())
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::defaultPoints($get, $set)),
                TextInput::make('points')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->helperText('OOFscore points; follows the tier, and is 0 off the OOF shelf.'),
                CheckboxList::make('session_types')
                    ->label('Sessions')
                    ->options(collect(SessionType::cases())->mapWithKeys(fn (SessionType $type) => [$type->value => $type->label()])->all())
                    ->columns(4)
                    ->required()
                    ->columnSpanFull(),
                Select::make('supersedes')
                    ->multiple()
                    ->options(fn (?Achievement $record): array => Achievement::query()
                        ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                        ->orderBy('name')
                        ->pluck('name', 'key')
                        ->all())
                    ->helperText('Achievements this one replaces when both come from the same event for the same car.')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Trigger')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('stacks')
                    ->helperText('Awarded once per instance; otherwise once per driver per session.'),
                Toggle::make('is_enabled')
                    ->label('Enabled'),
            ]);
    }

    private static function defaultPoints(Get $get, Set $set): void
    {
        $tier = AchievementTier::tryFrom((string) $get('tier'));

        $set('points', $get('shelf') === AchievementShelf::Oof->value && $tier !== null ? $tier->points() : 0);
    }
}
