<?php

declare(strict_types=1);

namespace App\Tests\Service\Observation\Meteorology;

use App\Service\Observation\Meteorology\WindRose;
use PHPUnit\Framework\TestCase;

final class WindRoseTest extends TestCase
{
    public function testEmptyInputYieldsEightZeroSectorsAndNoCalm(): void
    {
        $result = WindRose::tally([]);

        self::assertSame(0, $result['calm']);
        self::assertCount(8, $result['sectors']);

        $codes = array_column($result['sectors'], 'code');
        self::assertSame(['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'], $codes);

        foreach ($result['sectors'] as $sector) {
            self::assertSame(0, $sector['count']);
        }
    }

    public function testSectorsCarryTranslationKeysNotLabels(): void
    {
        $result = WindRose::tally([1]);

        self::assertSame('wind.direction.n', $result['sectors'][0]['label']);
        self::assertSame('wind.direction.ne', $result['sectors'][1]['label']);
        self::assertSame('wind.direction.nw', $result['sectors'][7]['label']);
    }

    public function testCalmReadingsAreCountedSeparatelyAndExcludedFromSectors(): void
    {
        $result = WindRose::tally([0, 0, 0]);

        self::assertSame(3, $result['calm']);
        foreach ($result['sectors'] as $sector) {
            self::assertSame(0, $sector['count']);
        }
    }

    public function testCodeNineFoldsIntoNorth(): void
    {
        $result = WindRose::tally([1, 9, 9]);

        self::assertSame(3, $result['sectors'][0]['count']); // N
        self::assertSame(0, $result['calm']);
    }

    public function testNullAndUnknownIdsAreIgnored(): void
    {
        $result = WindRose::tally([null, 42, -1, 3]);

        self::assertSame(0, $result['calm']);
        self::assertSame(1, $result['sectors'][2]['count']); // E
        $total = array_sum(array_column($result['sectors'], 'count'));
        self::assertSame(1, $total);
    }

    public function testSectorsCarryNullAvgSpeedWhenNoSpeedsGiven(): void
    {
        $result = WindRose::tally([1, 3]);

        foreach ($result['sectors'] as $sector) {
            self::assertNull($sector['avg_speed']);
        }
    }

    public function testAverageSpeedIsComputedPerSector(): void
    {
        // Two N readings (10, 20 → avg 15), one E reading (30).
        $ids = [1, 1, 3];
        $speeds = [10.0, 20.0, 30.0];

        $result = WindRose::tally($ids, $speeds);

        $byCode = [];
        foreach ($result['sectors'] as $sector) {
            $byCode[$sector['code']] = $sector;
        }

        self::assertSame(15.0, $byCode['N']['avg_speed']);
        self::assertSame(30.0, $byCode['E']['avg_speed']);
        self::assertNull($byCode['S']['avg_speed']);
    }

    public function testNullSpeedsAreExcludedFromTheAverage(): void
    {
        // N gets one null speed and one 20 km/h reading → avg 20, count 2.
        $result = WindRose::tally([1, 1], [null, 20.0]);

        self::assertSame(2, $result['sectors'][0]['count']);
        self::assertSame(20.0, $result['sectors'][0]['avg_speed']);
    }

    public function testSpeedOfCalmReadingsIsIgnored(): void
    {
        // Calm (id 0) carries a speed but must not feed any sector average.
        $result = WindRose::tally([0, 1], [5.0, 12.0]);

        self::assertSame(1, $result['calm']);
        self::assertSame(12.0, $result['sectors'][0]['avg_speed']);
    }

    public function testMixedDistributionCountsEachDirection(): void
    {
        // 5×N, 3×E, 2×SW, 1×NW, 1 calm
        $ids = [1, 1, 1, 1, 1, 3, 3, 3, 6, 6, 8, 0];
        $result = WindRose::tally($ids);

        $byCode = [];
        foreach ($result['sectors'] as $sector) {
            $byCode[$sector['code']] = $sector['count'];
        }

        self::assertSame(5, $byCode['N']);
        self::assertSame(3, $byCode['E']);
        self::assertSame(2, $byCode['SW']);
        self::assertSame(1, $byCode['NW']);
        self::assertSame(0, $byCode['NE']);
        self::assertSame(1, $result['calm']);
    }
}
