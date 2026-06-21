<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Forecast\Oceanography\TideExtrema;
use PHPUnit\Framework\TestCase;

final class TideExtremaTest extends TestCase
{
    public function testReturnsEmptyForShortSeries(): void
    {
        self::assertSame([], TideExtrema::findInSeries([]));
        self::assertSame([], TideExtrema::findInSeries([['t' => 0, 'h' => 1.0]]));
        self::assertSame([], TideExtrema::findInSeries([
            ['t' => 0, 'h' => 1.0],
            ['t' => 1, 'h' => 2.0],
        ]));
    }

    public function testDetectsSingleHighAndLow(): void
    {
        // [0, 1, 0, -1, 0] → high at index 1, low at index 3
        $series = [
            ['t' => 0,  'h' => 0.0],
            ['t' => 10, 'h' => 1.0],
            ['t' => 20, 'h' => 0.0],
            ['t' => 30, 'h' => -1.0],
            ['t' => 40, 'h' => 0.0],
        ];

        $extrema = TideExtrema::findInSeries($series);

        self::assertSame(
            [
                ['t' => 10, 'h' => 1.0, 'type' => 'high'],
                ['t' => 30, 'h' => -1.0, 'type' => 'low'],
            ],
            $extrema,
        );
    }

    public function testFindsRoughlyFourExtremaPerDayForSemidiurnalCosine(): void
    {
        // M2-like semidiurnal cosine (period 12.42h) sampled every 10 min
        // across 24h produces ~4 extrema (2 highs + 2 lows).
        $series = [];
        $step = 10 * 60;
        $omega = 2 * M_PI / (12.42 * 3600);
        for ($i = 0; $i < 144; $i++) {
            $t = $i * $step;
            $series[] = ['t' => $t, 'h' => cos($omega * $t)];
        }

        $extrema = TideExtrema::findInSeries($series);

        self::assertGreaterThanOrEqual(3, count($extrema));
        self::assertLessThanOrEqual(5, count($extrema));
    }

    public function testDetectsFlatPeaksAndValleysAsSingleExtremum(): void
    {
        // A flat peak and a flat valley (two consecutive equal values)
        // must each be reported once, not ignored or duplicated.
        $series = [
            ['t' => 0,  'h' => 1.0],
            ['t' => 10, 'h' => 2.0],
            ['t' => 20, 'h' => 2.0], // flat peak
            ['t' => 30, 'h' => 1.5],
            ['t' => 40, 'h' => 1.0],
            ['t' => 50, 'h' => 1.0], // flat valley
            ['t' => 60, 'h' => 2.0],
        ];

        $extrema = TideExtrema::findInSeries($series);

        self::assertSame(
            [
                ['t' => 15, 'h' => 2.0, 'type' => 'high'],
                ['t' => 45, 'h' => 1.0, 'type' => 'low'],
            ],
            $extrema,
        );
    }
}
