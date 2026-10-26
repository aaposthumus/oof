<?php

namespace Tests\Unit\Normalizers\Jolpica\Support;

use App\Normalizers\Jolpica\Support\TimeParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeParserTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?int}>
     */
    public static function times(): array
    {
        return [
            'lap time' => ['1:38.406', 98_406],
            'pit stop seconds' => ['22.456', 22_456],
            'red flag pit stop' => ['16:44.123', 1_004_123],
            'race time with hours' => ['1:40:52.554', 6_052_554],
            'one decimal' => ['2:13:23.6', 8_003_600],
            'no decimals' => ['1:41', 101_000],
            'gap to winner' => ['+13.773', null],
            'did not finish' => ['DNF', null],
            'empty' => ['', null],
            'missing' => [null, null],
        ];
    }

    #[DataProvider('times')]
    public function test_converts_time_to_milliseconds(?string $time, ?int $expected): void
    {
        $this->assertSame($expected, TimeParser::toMillis($time));
    }
}
