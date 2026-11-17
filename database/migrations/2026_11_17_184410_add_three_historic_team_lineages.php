<?php

use App\Models\TeamLineage;
use App\Normalizers\Jolpica\Support\TeamLineageSync;
use Illuminate\Database\Migrations\Migration;

/**
 * Three more historic renames: Coloni to Andrea Moda, Ensign to Theodore
 * and Onyx to Monteverdi. Imported season teams are re-pointed at once.
 * On a fresh database the create migration has seeded them already.
 */
return new class extends Migration
{
    /**
     * @var list<array{jolpica_constructor_id: string, team_slug: string, note: string|null}>
     */
    private const RULES = [
        ['jolpica_constructor_id' => 'coloni', 'team_slug' => 'moda', 'note' => 'Andrea Moda bought Coloni for 1992.'],
        ['jolpica_constructor_id' => 'moda', 'team_slug' => 'moda', 'note' => null],
        ['jolpica_constructor_id' => 'ensign', 'team_slug' => 'theodore', 'note' => 'Ensign merged into Theodore for 1983. Both entered cars in 1978, 1981 and 1982.'],
        ['jolpica_constructor_id' => 'theodore', 'team_slug' => 'theodore', 'note' => null],
        ['jolpica_constructor_id' => 'onyx', 'team_slug' => 'monteverdi', 'note' => 'Peter Monteverdi took over Onyx during 1990.'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::RULES as $rule) {
            TeamLineage::firstOrCreate(
                ['jolpica_constructor_id' => $rule['jolpica_constructor_id'], 'from_year' => null, 'to_year' => null],
                ['team_slug' => $rule['team_slug'], 'note' => $rule['note']],
            );
        }

        app(TeamLineageSync::class)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::RULES as $rule) {
            TeamLineage::query()
                ->where('jolpica_constructor_id', $rule['jolpica_constructor_id'])
                ->whereNull('from_year')
                ->whereNull('to_year')
                ->where('team_slug', $rule['team_slug'])
                ->delete();
        }

        app(TeamLineageSync::class)->run();
    }
};
