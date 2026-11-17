<?php

namespace App\Filament\Resources\TeamLineages;

use App\Filament\Resources\TeamLineages\Pages\CreateTeamLineage;
use App\Filament\Resources\TeamLineages\Pages\EditTeamLineage;
use App\Filament\Resources\TeamLineages\Pages\ListTeamLineages;
use App\Filament\Resources\TeamLineages\Schemas\TeamLineageForm;
use App\Filament\Resources\TeamLineages\Tables\TeamLineagesTable;
use App\Models\TeamLineage;
use App\Normalizers\Jolpica\Support\TeamLineageSync;
use BackedEnum;
use Closure;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Which Jolpica constructors belong to one lasting team. Every change
 * re-points the imported season teams at once, so team form and team
 * history follow it.
 */
class TeamLineageResource extends Resource
{
    protected static ?string $model = TeamLineage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $navigationLabel = 'Team lineage';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return TeamLineageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeamLineagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamLineages::route('/'),
            'create' => CreateTeamLineage::route('/create'),
            'edit' => EditTeamLineage::route('/{record}/edit'),
        ];
    }

    /**
     * Make a change to the rules and re-point the season teams in one
     * transaction; a change the resolver refuses is rolled back.
     *
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     *
     * @throws Halt
     */
    public static function applying(Closure $change): mixed
    {
        try {
            [$result, $sync] = DB::transaction(fn () => [$change(), app(TeamLineageSync::class)->run()]);
        } catch (LogicException $exception) {
            Notification::make()->danger()->title('Lineage not changed')->body($exception->getMessage())->send();

            throw new Halt;
        }

        Notification::make()
            ->success()
            ->title('Lineage applied')
            ->body("{$sync['moved']} season teams moved, {$sync['removed_teams']} teams left without seasons removed.")
            ->send();

        return $result;
    }
}
