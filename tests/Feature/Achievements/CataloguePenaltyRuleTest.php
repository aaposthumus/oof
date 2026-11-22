<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Catalogue;
use Tests\TestCase;

/**
 * The standing rule: an achievement never supersedes a penalty stamp of a
 * higher tier than its own, so a heavier penalty never earns fewer points
 * while an achievement is worth the same everywhere in a season. A new
 * penalty or offence stamp that breaks it fails here.
 */
class CataloguePenaltyRuleTest extends TestCase
{
    private const TIERS = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];

    public function test_no_achievement_supersedes_a_penalty_stamp_of_a_higher_tier(): void
    {
        $this->assertSame([], $this->violations(), 'An achievement must not supersede a heavier penalty stamp.');
    }

    public function test_a_catalogue_entry_that_breaks_the_rule_is_found(): void
    {
        config(['achievement_catalogue.achievements.bumper_cars.supersedes' => ['high_five', 'perfect_ten', 'drive_thru']]);

        $this->assertSame(['bumper_cars (silver) supersedes perfect_ten (gold)', 'bumper_cars (silver) supersedes drive_thru (platinum)'], $this->violations());
    }

    /**
     * @return list<string>
     */
    private function violations(): array
    {
        $entries = app(Catalogue::class)->entries();
        $violations = [];

        foreach ($entries as $key => $entry) {
            foreach ($entry['supersedes'] ?? [] as $superseded) {
                if (($entries[$superseded]['section'] ?? null) !== 'penalty') {
                    continue;
                }

                if ((self::TIERS[$entry['tier'] ?? ''] ?? 0) < self::TIERS[$entries[$superseded]['tier']]) {
                    $violations[] = "{$key} ({$entry['tier']}) supersedes {$superseded} ({$entries[$superseded]['tier']})";
                }
            }
        }

        return $violations;
    }

    public function test_the_rule_has_penalty_stamps_to_check(): void
    {
        $entries = app(Catalogue::class)->entries();
        $penalties = array_keys(array_filter($entries, fn (array $entry) => $entry['section'] === 'penalty'));

        $this->assertEqualsCanonicalizing(['high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'], $penalties);
        $this->assertContains('high_five', $entries['bumper_cars']['supersedes']);
        $this->assertNotContains('perfect_ten', $entries['bumper_cars']['supersedes']);
        $this->assertNotContains('drive_thru', $entries['itchy_feet']['supersedes']);
    }
}
