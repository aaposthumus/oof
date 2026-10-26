<?php

namespace Tests\Unit\Normalizers\Jolpica\Support;

use App\Normalizers\Jolpica\Support\TeamResolver;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeamResolverTest extends TestCase
{
    /**
     * @return list<array{constructor: string, team: string, from?: int, to?: int}>
     */
    private function lineage(): array
    {
        return [
            ['constructor' => 'sauber', 'team' => 'audi'],
            ['constructor' => 'alfa', 'from' => 2019, 'to' => 2023, 'team' => 'audi'],
            ['constructor' => 'audi', 'team' => 'audi'],
            ['constructor' => 'aston_martin', 'from' => 2021, 'team' => 'aston_martin'],
        ];
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function constructors(): array
    {
        return [
            'renamed team' => ['sauber', 2024, 'audi'],
            'reused id inside its range' => ['alfa', 2019, 'audi'],
            'reused id before its range' => ['alfa', 1950, 'alfa'],
            'open-ended range' => ['aston_martin', 2026, 'aston_martin'],
            'unmapped constructor' => ['ferrari', 2024, 'ferrari'],
        ];
    }

    #[DataProvider('constructors')]
    public function test_maps_constructor_to_lasting_team(string $constructorId, int $year, string $expected): void
    {
        $this->assertSame($expected, (new TeamResolver($this->lineage()))->slugFor($constructorId, $year));
    }

    public function test_rejects_unmapped_constructor_that_would_merge_into_a_lineage_team(): void
    {
        $this->expectException(LogicException::class);

        (new TeamResolver($this->lineage()))->slugFor('aston_martin', 1959);
    }
}
