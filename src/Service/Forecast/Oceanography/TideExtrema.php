<?php

declare(strict_types=1);

namespace App\Service\Forecast\Oceanography;

/**
 * Detects local maxima (high tide) and local minima (low tide) in a
 * sampled tide series. Resolution is bounded by the sample interval —
 * with 10-minute samples, extrema timing is accurate to ±5 minutes.
 *
 * Runs of consecutive equal values are treated as a single extremum
 * so that flat peaks/valleys (caused by rounding or by the true peak
 * landing between two sample points) are not discarded.
 */
final class TideExtrema
{
    /**
     * @param list<array{t: int, h: float}> $series
     *
     * @return list<array{t: int, h: float, type: string}>
     */
    public static function findInSeries(array $series): array
    {
        $extrema = [];
        $n = count($series);
        if ($n < 3) {
            return [];
        }

        $i = 0;
        while ($i < $n - 1) {
            $j = $i;
            while ($j < $n - 1 && $series[$j]['h'] == $series[$j + 1]['h']) {
                $j++;
            }

            $prev = $i > 0 ? $series[$i - 1]['h'] : null;
            $next = $j < $n - 1 ? $series[$j + 1]['h'] : null;
            $curr = $series[$i]['h'];

            if ($prev !== null && $next !== null) {
                if ($curr > $prev && $curr > $next) {
                    $extrema[] = [
                        't' => (int) (($series[$i]['t'] + $series[$j]['t']) / 2),
                        'h' => $curr,
                        'type' => 'high',
                    ];
                } elseif ($curr < $prev && $curr < $next) {
                    $extrema[] = [
                        't' => (int) (($series[$i]['t'] + $series[$j]['t']) / 2),
                        'h' => $curr,
                        'type' => 'low',
                    ];
                }
            }

            $i = $j + 1;
        }

        return $extrema;
    }
}
