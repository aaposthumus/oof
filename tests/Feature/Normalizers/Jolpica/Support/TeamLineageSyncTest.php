<?php

namespace Tests\Feature\Normalizers\Jolpica\Support;

use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\TeamLineage;
use App\Normalizers\Jolpica\Support\TeamLineageSync;
use App\Normalizers\Jolpica\Support\TeamResolver;
use Database\Seeders\TeamLineageSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class TeamLineageSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function seasonTeam(int $year, string $constructorId, string $name): SeasonTeam
    {
        $team = Team::firstOrCreate(['slug' => $constructorId], ['name' => $name]);

        return SeasonTeam::factory()->create(['season_id' => Season::firstOrCreate(['year' => $year])->id, 'team_id' => $team->id, 'jolpica_constructor_id' => $constructorId, 'name' => $name]);
    }

    public function test_the_migration_seeds_the_lineage_with_the_historic_renames(): void
    {
        $this->assertSame(count(TeamLineageSeeder::RULES), TeamLineage::count());
        $this->assertSame(TeamLineage::count(), TeamLineage::all()->unique(fn (TeamLineage $rule) => "{$rule->jolpica_constructor_id}|{$rule->from_year}|{$rule->to_year}")->count());

        $resolver = app(TeamResolver::class);
        $this->assertSame('arrows', $resolver->slugFor('footwork', 1993));
        $this->assertSame('march', $resolver->slugFor('leyton', 1990));
        $this->assertSame('audi', $resolver->slugFor('alfa', 2020));
        $this->assertSame('alfa', $resolver->slugFor('alfa', 1950));
        $this->assertSame('moda', $resolver->slugFor('coloni', 1989));
        $this->assertSame('monteverdi', $resolver->slugFor('onyx', 1990));
    }

    public function test_ensign_and_theodore_stay_apart_while_both_raced_and_join_from_1983(): void
    {
        $ensign = $this->seasonTeam(1981, 'ensign', 'Ensign');
        $earlyTheodore = $this->seasonTeam(1981, 'theodore', 'Theodore');
        $theodore = $this->seasonTeam(1983, 'theodore', 'Theodore');

        app(TeamLineageSync::class)->run();

        $this->assertNotSame($ensign->fresh()->team_id, $earlyTheodore->fresh()->team_id);
        $this->assertSame('theodore_1978', $earlyTheodore->fresh()->team->slug);
        $this->assertSame('theodore', $ensign->fresh()->team->slug);
        $this->assertSame($ensign->fresh()->team_id, $theodore->fresh()->team_id);
    }

    public function test_re_points_season_teams_removes_emptied_teams_and_takes_the_latest_name(): void
    {
        TeamLineage::query()->delete();
        $old = $this->seasonTeam(1990, 'leyton', 'Leyton House');
        $new = $this->seasonTeam(1992, 'march', 'March');
        TeamLineage::factory()->create(['jolpica_constructor_id' => 'leyton', 'team_slug' => 'march']);
        TeamLineage::factory()->create(['jolpica_constructor_id' => 'march', 'team_slug' => 'march']);

        $this->assertSame(['moved' => 1, 'removed_teams' => 1], app(TeamLineageSync::class)->run());

        $this->assertSame($new->team_id, $old->fresh()->team_id);
        $this->assertNull(Team::firstWhere('slug', 'leyton'));
        $this->assertSame('March', $new->team->fresh()->name);
        $this->assertSame(['moved' => 0, 'removed_teams' => 0], app(TeamLineageSync::class)->run());
    }

    public function test_a_rule_that_would_merge_an_unmapped_constructor_changes_nothing(): void
    {
        TeamLineage::query()->delete();
        $leyton = $this->seasonTeam(1990, 'leyton', 'Leyton House');
        $this->seasonTeam(1992, 'march', 'March');
        TeamLineage::factory()->create(['jolpica_constructor_id' => 'leyton', 'team_slug' => 'march']);

        try {
            app(TeamLineageSync::class)->run();
            $this->fail('Expected a LogicException.');
        } catch (LogicException) {
            $this->assertSame('leyton', $leyton->fresh()->team->slug);
        }
    }
}
