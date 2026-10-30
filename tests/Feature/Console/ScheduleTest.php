<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_digs_once_a_day_after_the_import_and_normalize_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events());
        $dig = $events->sole(fn (Event $event) => str_contains($event->command, 'oof:dig'));
        $normalize = $events->sole(fn (Event $event) => str_contains($event->command, 'oof:normalize'));

        $this->assertSame('50 4 * * *', $dig->expression);
        $this->assertTrue($dig->withoutOverlapping);
        $this->assertSame('30 * * * *', $normalize->expression);
    }

    public function test_imports_openf1_hourly_after_the_jolpica_import_and_before_normalizing(): void
    {
        $import = collect(app(Schedule::class)->events())->sole(fn (Event $event) => str_contains($event->command, 'oof:import --source=openf1'));

        $this->assertSame('15 * * * *', $import->expression);
        $this->assertTrue($import->withoutOverlapping);
    }

    public function test_unlocks_achievements_hourly_after_normalizing(): void
    {
        $achievements = collect(app(Schedule::class)->events())->sole(fn (Event $event) => str_contains($event->command, 'oof:achievements --changed'));

        $this->assertSame('50 * * * *', $achievements->expression);
        $this->assertTrue($achievements->withoutOverlapping);
    }
}
