<?php

namespace Tests\Feature\Normalizers\Jolpica\Support;

use App\Normalizers\Jolpica\Support\RoundNameResolver;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RoundNameResolverTest extends TestCase
{
    public function test_resolves_grand_prix_held_in_another_country_to_its_own_name(): void
    {
        $resolved = app(RoundNameResolver::class)->resolve('Bahrain Grand Prix in Malaysia', 2026, 16);

        $this->assertSame([
            'name' => 'Bahrain Grand Prix',
            'short_name' => 'Bahrain',
            'country_name' => 'Bahrain',
            'country_code' => 'BHR',
        ], $resolved);
    }

    public function test_round_override_takes_precedence(): void
    {
        $override = ['name' => 'Grand Prix of Somewhere', 'short_name' => 'Somewhere', 'country_name' => null, 'country_code' => null];
        config(['f1.round_overrides' => ['2026-16' => $override]]);

        $this->assertSame($override, app(RoundNameResolver::class)->resolve('Bahrain Grand Prix in Malaysia', 2026, 16));
    }

    public function test_falls_back_to_source_name_and_logs_unknown_grand_prix(): void
    {
        Log::shouldReceive('warning')->once();

        $resolved = app(RoundNameResolver::class)->resolve('Atlantis Grand Prix', 2027, 1);

        $this->assertSame(['name' => 'Atlantis Grand Prix', 'short_name' => 'Atlantis', 'country_name' => null, 'country_code' => null], $resolved);
    }
}
