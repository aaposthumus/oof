<?php

namespace Tests\Unit\Normalizers\OpenF1;

use App\Models\Driver;
use App\Normalizers\OpenF1\Support\NameMatcher;
use PHPUnit\Framework\TestCase;

class NameMatcherTest extends TestCase
{
    private function driver(string $given, string $family): Driver
    {
        return new Driver(['given_name' => $given, 'family_name' => $family]);
    }

    public function test_ignores_case_accents_and_extra_given_names(): void
    {
        $this->assertTrue(NameMatcher::sameDriver($this->driver('Nico', 'Hülkenberg'), ['first_name' => 'Nico', 'last_name' => 'Hulkenberg']));
        $this->assertTrue(NameMatcher::sameDriver($this->driver('Andrea Kimi', 'Antonelli'), ['first_name' => 'Kimi', 'last_name' => 'ANTONELLI']));
        $this->assertTrue(NameMatcher::sameDriver($this->driver('Nyck', 'de Vries'), ['first_name' => 'Nyck', 'last_name' => 'De Vries']));
    }

    public function test_accepts_names_in_swapped_order(): void
    {
        $this->assertTrue(NameMatcher::sameDriver($this->driver('Guanyu', 'Zhou'), ['first_name' => 'Zhou', 'last_name' => 'Guanyu']));
    }

    public function test_needs_the_given_name_too(): void
    {
        $this->assertFalse(NameMatcher::sameDriver($this->driver('Jim', 'Crawford'), ['first_name' => 'Jak', 'last_name' => 'Crawford']));
        $this->assertFalse(NameMatcher::sameDriver($this->driver('Arthur', 'Leclerc'), ['first_name' => 'Charles', 'last_name' => 'Leclerc']));
        $this->assertFalse(NameMatcher::sameDriver($this->driver('Max', 'Verstappen'), ['first_name' => null, 'last_name' => null]));
    }
}
