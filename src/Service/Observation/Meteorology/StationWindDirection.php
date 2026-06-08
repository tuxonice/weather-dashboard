<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

/**
 * Helper for IPMA's `idDireccVento` field used in station observations.
 *
 * IPMA encodes wind direction as an integer 0-9 (0 = no wind; 1-8 are the
 * eight cardinal / inter-cardinal directions; 9 wraps back to North).
 */
final class StationWindDirection
{
    /** @var array<int, array{code: string, label: string, bearing: ?int}> */
    private const MAP = [
        0 => ['code' => '',   'label' => 'wind.direction.none', 'bearing' => null],
        1 => ['code' => 'N',  'label' => 'wind.direction.n',    'bearing' => 0],
        2 => ['code' => 'NE', 'label' => 'wind.direction.ne',   'bearing' => 45],
        3 => ['code' => 'E',  'label' => 'wind.direction.e',    'bearing' => 90],
        4 => ['code' => 'SE', 'label' => 'wind.direction.se',   'bearing' => 135],
        5 => ['code' => 'S',  'label' => 'wind.direction.s',    'bearing' => 180],
        6 => ['code' => 'SW', 'label' => 'wind.direction.sw',   'bearing' => 225],
        7 => ['code' => 'W',  'label' => 'wind.direction.w',    'bearing' => 270],
        8 => ['code' => 'NW', 'label' => 'wind.direction.nw',   'bearing' => 315],
        9 => ['code' => 'N',  'label' => 'wind.direction.n',    'bearing' => 0],
    ];

    public static function code(?int $id): string
    {
        if ($id === null) {
            return '';
        }

        return self::MAP[$id]['code'] ?? '';
    }

    /**
     * Translation key for the wind direction; resolve with `|trans` in Twig.
     */
    public static function label(?int $id): string
    {
        if ($id === null) {
            return 'wind.direction.unknown';
        }

        return self::MAP[$id]['label'] ?? 'wind.direction.unknown';
    }

    /**
     * Compass bearing, in degrees clockwise from North, that the wind blows
     * *from* (0 = N, 90 = E, …). Null for calm (id 0), missing, or unknown
     * ids — i.e. whenever there is no defined direction to draw.
     */
    public static function bearing(?int $id): ?int
    {
        if ($id === null) {
            return null;
        }

        return self::MAP[$id]['bearing'] ?? null;
    }
}
