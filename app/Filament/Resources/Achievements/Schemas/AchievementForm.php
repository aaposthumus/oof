<?php

namespace App\Filament\Resources\Achievements\Schemas;

use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\ScoreCategory;
use App\Enums\SessionType;
use App\Models\Achievement;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    ->helperText('Ties the achievement to its entry in the catalogue (config/achievement_catalogue.php).'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('shelf')
                    ->options(collect(AchievementShelf::cases())->mapWithKeys(fn (AchievementShelf $shelf) => [$shelf->value => $shelf->label()])->all())
                    ->helperText('Only the OOF shelf earns OOFscore points.')
                    ->required(),
                Select::make('section')
                    ->options(collect(AchievementSection::cases())->mapWithKeys(fn (AchievementSection $section) => [$section->value => $section->label()])->all())
                    ->helperText('Offence and penalty achievements need a message naming exactly one car.')
                    ->required(),
                Select::make('tier')
                    ->options(collect(AchievementTier::cases())->mapWithKeys(fn (AchievementTier $tier) => [$tier->value => "{$tier->label()} (".config("achievements.points.tiers.{$tier->value}").' points)'])->all())
                    ->helperText('The points per tier are set in config/achievements.php.'),
                Select::make('score_category')
                    ->label('Score category')
                    ->options(collect(ScoreCategory::cases())->mapWithKeys(fn (ScoreCategory $category) => [$category->value => $category->label()])->all())
                    ->required()
                    ->helperText('Pace and reliability are weighed in the OOFscore by the team\'s form before the round (multipliers in config/achievements.php). A change reaches the seasons that are not closed.'),
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
}
