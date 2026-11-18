<?php

namespace App\Filament\Resources\Achievements\Schemas;

use App\Achievements\Facts\DataSource;
use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\AwardScope;
use App\Enums\RecipientType;
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
    private const CATALOGUE_OWNED = 'Set in config/achievement_catalogue.php and written by oof:achievements:sync.';

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
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText(self::CATALOGUE_OWNED)
                    ->columnSpanFull(),
                TextInput::make('recipient_type')
                    ->label('Awarded to')
                    ->formatStateUsing(fn (?Achievement $record): string => ($record?->recipientType() ?? RecipientType::Driver)->label())
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText(self::CATALOGUE_OWNED),
                TextInput::make('scope')
                    ->label('Decided over')
                    ->formatStateUsing(fn (?Achievement $record): string => match ($record?->scope) {
                        AwardScope::Weekend => $record->decided_after === 'practice' ? 'The weekend, after the last practice session' : 'The weekend, after the '.(SessionType::tryFrom((string) $record->decided_after)?->label() ?? $record->decided_after),
                        AwardScope::Season => 'The season, once its final round is final',
                        default => 'Each session',
                    })
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('A weekend or season achievement reads the sessions above. '.self::CATALOGUE_OWNED)
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
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Awarded once per instance; otherwise once per driver per session. '.self::CATALOGUE_OWNED),
                Toggle::make('is_enabled')
                    ->label('Enabled'),
                TextInput::make('data_sources')
                    ->label('Data')
                    ->formatStateUsing(fn (?array $state): string => collect($state ?? [])->map(fn (string $source) => DataSource::tryFrom($source)?->label() ?? $source)->implode(', '))
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Derived from the trigger by oof:achievements:sync.'),
                TextInput::make('available_from')
                    ->label('Data from')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('The first season its data supports; the engine skips earlier sessions.'),
            ]);
    }
}
