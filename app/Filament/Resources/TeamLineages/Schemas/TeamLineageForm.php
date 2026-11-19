<?php

namespace App\Filament\Resources\TeamLineages\Schemas;

use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\TeamLineage;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TeamLineageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('jolpica_constructor_id')
                    ->label('Jolpica constructor ID')
                    ->required()
                    ->maxLength(64)
                    ->datalist(fn (): array => SeasonTeam::query()->distinct()->orderBy('jolpica_constructor_id')->pluck('jolpica_constructor_id')->all())
                    ->rule(fn (Get $get, ?TeamLineage $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        if (TeamLineage::overlaps((string) $value, self::year($get('from_year')), self::year($get('to_year')), $record?->id)) {
                            $fail('Another rule for this constructor already covers some of these seasons.');
                        }
                    }),
                TextInput::make('team_slug')
                    ->label('Lasting team (slug)')
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[a-z0-9_-]+$/')
                    ->datalist(fn (): array => Team::query()->orderBy('slug')->pluck('slug')->all())
                    ->helperText('An existing team, or a new slug for a new lasting team. By convention the slug of its latest name.'),
                TextInput::make('team_name')
                    ->label('Fixed team name')
                    ->maxLength(255)
                    ->helperText('Optional. The lasting team keeps this name instead of taking the name of its latest season. Set it on one rule of the team; the first rule with a name wins.'),
                TextInput::make('from_year')
                    ->label('From season')
                    ->integer()
                    ->minValue(1950)
                    ->helperText('Leave empty for no lower limit.'),
                TextInput::make('to_year')
                    ->label('To season')
                    ->integer()
                    ->minValue(1950)
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $fromYear = self::year($get('from_year'));

                        if ($fromYear !== null && self::year($value) !== null && self::year($value) < $fromYear) {
                            $fail('The last season comes before the first.');
                        }
                    })
                    ->helperText('Leave empty for no upper limit.'),
                TextInput::make('note')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    private static function year(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
