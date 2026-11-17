<?php

use App\Models\TeamLineage;
use App\Normalizers\Jolpica\Support\TeamLineageSync;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensign and Theodore both entered cars in 1978, 1981 and 1982, so they
 * stay separate teams until 1983, when Ensign merged into Theodore: the
 * lasting team theodore holds Ensign's seasons and Theodore's from 1983,
 * and Theodore's own entries before that are the team theodore_1978. A rule
 * already changed in the CMS is left alone.
 */
return new class extends Migration
{
    private const ENSIGN_NOTE_BEFORE = 'Ensign merged into Theodore for 1983. Both entered cars in 1978, 1981 and 1982.';

    private const ENSIGN_NOTE_AFTER = 'Ensign merged into Theodore for 1983; from then on Theodore carries Ensign\'s history.';

    private const EARLY_NOTE = 'Theodore\'s own entries of 1978 to 1982, separate from Ensign, which raced in the same seasons.';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $open = TeamLineage::query()
            ->where('jolpica_constructor_id', 'theodore')->whereNull('from_year')->whereNull('to_year')->where('team_slug', 'theodore');

        // On a fresh database the seeder has the split rules already; the open one comes from the earlier migration.
        if (TeamLineage::query()->where('jolpica_constructor_id', 'theodore')->where('from_year', 1983)->exists()) {
            $open->delete();
        } else {
            $open->update(['from_year' => 1983]);
        }

        if (TeamLineage::query()->where('jolpica_constructor_id', 'theodore')->where('from_year', 1983)->exists()) {
            TeamLineage::firstOrCreate(
                ['jolpica_constructor_id' => 'theodore', 'from_year' => null, 'to_year' => 1982],
                ['team_slug' => 'theodore_1978', 'note' => self::EARLY_NOTE],
            );
        }

        TeamLineage::query()->where('jolpica_constructor_id', 'ensign')->where('note', self::ENSIGN_NOTE_BEFORE)->update(['note' => self::ENSIGN_NOTE_AFTER]);

        app(TeamLineageSync::class)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        TeamLineage::query()
            ->where('jolpica_constructor_id', 'theodore')->whereNull('from_year')->where('to_year', 1982)->where('team_slug', 'theodore_1978')
            ->delete();

        TeamLineage::query()
            ->where('jolpica_constructor_id', 'theodore')->where('from_year', 1983)->whereNull('to_year')->where('team_slug', 'theodore')
            ->update(['from_year' => null]);

        TeamLineage::query()->where('jolpica_constructor_id', 'ensign')->where('note', self::ENSIGN_NOTE_AFTER)->update(['note' => self::ENSIGN_NOTE_BEFORE]);

        app(TeamLineageSync::class)->run();
    }
};
