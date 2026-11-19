<?php

namespace Tests\Feature\Normalizers\Jolpica\Support;

use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\TeamLineage;
use App\Normalizers\Jolpica\Support\CoreResolver;
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
        $this->assertSame('alfa_1950s', $resolver->slugFor('alfa', 1950));
        $this->assertSame('moda', $resolver->slugFor('coloni', 1989));
        $this->assertSame('monteverdi', $resolver->slugFor('onyx', 1990));
    }

    public function test_chassis_engine_constructors_join_their_chassis_maker(): void
    {
        $resolver = app(TeamResolver::class);

        $this->assertSame('team_lotus', $resolver->slugFor('lotus-climax', 1963));
        $this->assertSame('team_lotus', $resolver->slugFor('team_lotus', 1978));
        $this->assertSame('cooper', $resolver->slugFor('cooper-maserati', 1966));
        $this->assertSame('brabham', $resolver->slugFor('brabham-repco', 1967));
        $this->assertSame('mclaren', $resolver->slugFor('mclaren-ford', 1968));
        $this->assertSame('march', $resolver->slugFor('march-ford', 1971));
        $this->assertSame('eagle', $resolver->slugFor('eagle-weslake', 1967));
        $this->assertSame('matra', $resolver->slugFor('matra-ford', 1969));
        $this->assertSame('tomaso', $resolver->slugFor('de_tomaso-osca', 1961));
    }

    public function test_a_team_under_another_id_joins_its_lasting_team_and_shared_ids_split_by_season(): void
    {
        $resolver = app(TeamResolver::class);

        $this->assertSame('gordini', $resolver->slugFor('simca', 1951));
        $this->assertSame('wolf', $resolver->slugFor('iso_marlboro', 1974));
        $this->assertSame('wolf', $resolver->slugFor('williams', 1976));
        $this->assertSame('williams', $resolver->slugFor('williams', 1977));
        $this->assertSame('hill', $resolver->slugFor('lola', 1974));
        $this->assertSame('lola', $resolver->slugFor('lola', 1986));
        $this->assertSame('larrousse', $resolver->slugFor('lola', 1991));
        $this->assertSame('scuderia_italia', $resolver->slugFor('lola', 1993));
        $this->assertSame('scuderia_italia', $resolver->slugFor('dallara', 1990));
        $this->assertSame('march', $resolver->slugFor('march', 1980));
        $this->assertSame('ram', $resolver->slugFor('march', 1982));
        $this->assertSame('march', $resolver->slugFor('march', 1988));
        $this->assertSame('alfa_special', $resolver->slugFor('alfa', 1965));
        $this->assertSame('alfa', $resolver->slugFor('alfa', 1983));
        $this->assertSame('ats_1963', $resolver->slugFor('ats', 1963));
        $this->assertSame('ats', $resolver->slugFor('ats', 1980));
    }

    public function test_a_fixed_name_wins_over_the_latest_seasons_name(): void
    {
        $early = $this->seasonTeam(1958, 'cooper', 'Cooper');
        $this->seasonTeam(1969, 'cooper-maserati', 'Cooper-Maserati');

        app(TeamLineageSync::class)->run();

        $this->assertSame('Cooper', $early->fresh()->team->name);
        $this->assertSame(2, $early->fresh()->team->seasonTeams()->count());
    }

    public function test_importing_a_later_season_keeps_the_fixed_name(): void
    {
        $this->seasonTeam(1990, 'dallara', 'Dallara');
        app(TeamLineageSync::class)->run();

        app(CoreResolver::class)->seasonTeam(Season::firstOrCreate(['year' => 1993]), ['constructorId' => 'lola', 'name' => 'Lola']);

        $team = Team::firstWhere('slug', 'scuderia_italia');
        $this->assertSame('Scuderia Italia', $team->name);
        $this->assertSame(['Dallara', 'Lola'], $team->seasonTeams()->orderBy('name')->pluck('name')->all());
    }

    public function test_clearing_a_fixed_name_gives_the_team_its_latest_seasons_name_again(): void
    {
        $this->seasonTeam(1969, 'eagle-climax', 'Eagle-Climax');
        app(TeamLineageSync::class)->run();

        TeamLineage::query()->where('team_slug', 'eagle')->update(['team_name' => null]);
        app(TeamLineageSync::class)->run();

        $this->assertSame('Eagle-Climax', Team::firstWhere('slug', 'eagle')->name);
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
