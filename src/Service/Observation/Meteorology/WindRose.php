<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

/**
 * Aggregates a series of IPMA `idDireccVento` readings into an 8-sector wind
 * rose (frequency per compass direction) for the polar-area chart.
 *
 * Direction encoding mirrors {@see StationWindDirection}: 0 = calm,
 * 1-8 = the eight compass directions, 9 wraps back to North. Null and any
 * out-of-range id is ignored.
 */
final class WindRose
{
    /**
     * Sectors in clockwise drawing order, each as `[direction id, code,
     * translation key]`. Code 9 (North) folds onto id 1.
     *
     * @var list<array{int, string, string}>
     */
    private const SECTORS = [
        [1, 'N',  'wind.direction.n'],
        [2, 'NE', 'wind.direction.ne'],
        [3, 'E',  'wind.direction.e'],
        [4, 'SE', 'wind.direction.se'],
        [5, 'S',  'wind.direction.s'],
        [6, 'SW', 'wind.direction.sw'],
        [7, 'W',  'wind.direction.w'],
        [8, 'NW', 'wind.direction.nw'],
    ];

    /**
     * @param array<int, ?int>   $directionIds raw `idDireccVento` values
     * @param array<int, ?float> $speeds       wind speeds parallel to
     *                                          `$directionIds` by index (any
     *                                          unit; the average is returned in
     *                                          whatever unit is passed in).
     *                                          Pass `[]` to skip speed tallying.
     *
     * @return array{
     *     sectors: list<array{code: string, label: string, count: int, avg_speed: ?float}>,
     *     calm: int
     * }
     */
    public static function tally(array $directionIds, array $speeds = []): array
    {
        $counts = array_fill(1, 8, 0);
        $speedSums = array_fill(1, 8, 0.0);
        $speedCounts = array_fill(1, 8, 0);
        $calm = 0;

        foreach ($directionIds as $i => $id) {
            if ($id === 0) {
                $calm++;
                continue;
            }
            if ($id === 9) {
                $id = 1; // North wraps back to N
            }
            if ($id !== null && $id >= 1 && $id <= 8) {
                $counts[$id]++;

                $speed = $speeds[$i] ?? null;
                if ($speed !== null) {
                    $speedSums[$id] += $speed;
                    $speedCounts[$id]++;
                }
            }
        }

        $sectors = [];
        foreach (self::SECTORS as [$id, $code, $label]) {
            $sectors[] = [
                'code' => $code,
                'label' => $label,
                'count' => $counts[$id],
                'avg_speed' => $speedCounts[$id] > 0
                    ? $speedSums[$id] / $speedCounts[$id]
                    : null,
            ];
        }

        return ['sectors' => $sectors, 'calm' => $calm];
    }
}
