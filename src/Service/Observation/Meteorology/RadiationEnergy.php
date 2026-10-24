<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

use DateTimeImmutable;

/**
 * Estimates daily solar energy from a 24h series of radiation readings.
 */
final class RadiationEnergy
{
    private const MIN_READINGS = 20;
    private const MIN_SUNNY_READINGS = 5;

    /**
     * Integrates the readings with the trapezoidal rule:
     * Energy (Wh/m²) = Σ (W[i] + W[i+1]) / 2 × Δh, returned as kWh/m².
     *
     * Returns null when there are too few readings, or fewer than five
     * non-zero ones, for the figure to be meaningful.
     *
     * @param list<array{at: DateTimeImmutable, w: float}> $series Oldest → newest, nulls already dropped.
     */
    public static function kwhPerM2(array $series): ?float
    {
        $sunny = count(array_filter($series, static fn(array $r): bool => $r['w'] > 0.0));
        if (count($series) < self::MIN_READINGS || $sunny < self::MIN_SUNNY_READINGS) {
            return null;
        }

        $totalWh = 0.0;
        for ($i = 0; $i < count($series) - 1; $i++) {
            $deltaH = ($series[$i + 1]['at']->getTimestamp() - $series[$i]['at']->getTimestamp()) / 3600.0;
            $totalWh += ($series[$i]['w'] + $series[$i + 1]['w']) / 2.0 * $deltaH;
        }

        return $totalWh / 1000.0;
    }
}
