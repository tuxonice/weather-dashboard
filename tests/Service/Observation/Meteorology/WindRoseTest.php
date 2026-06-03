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
