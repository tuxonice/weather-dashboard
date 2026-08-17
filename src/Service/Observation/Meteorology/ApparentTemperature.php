<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

/**
 * Apparent ("feels like") temperature from a station reading.
 *
 * Uses Steadman's apparent-temperature scale (Steadman 1984, refined 1994) in
 * its simplified operational form: an approximation of his full model of heat
 * balance in the human body, folding humidity and wind into a single
 * continuous curve that covers both hot and cold conditions.
 *
 * The model represents an adult walking outdoors *in the shade*, so the result
 * does not account for direct sunlight. A radiation-inclusive variant of the
 * formula exists but is not used here, since not every IPMA station reports
 * radiation.
 *
 *     es = 6.105 · exp(17.27·T / (237.7 + T))     saturation vapour pressure (hPa)
 *     e  = (RH / 100) · es                        actual vapour pressure (hPa)
 *     AT = T + 0.33·e − 0.70·v − 4.00
 *
 * Note that `e` is the *water-vapour* partial pressure derived from
 * temperature and relative humidity — not the station's barometric
 * pressure, which has no term in this formula. Wind must be in m/s
 * (IPMA's `intensidadeVento`), not km/h.
 */
final class ApparentTemperature
{
    /**
     * Returns null when any required reading is missing. Zero is a valid
     * reading for humidity and wind, so only null counts as missing.
     */
    public static function celsius(?float $celsius, ?float $humidityPct, ?float $windMs): ?float
    {
        if ($celsius === null || $humidityPct === null || $windMs === null) {
            return null;
        }

        $saturationVapourPressure = 6.105 * exp(17.27 * $celsius / (237.7 + $celsius));
        $vapourPressure = $humidityPct / 100.0 * $saturationVapourPressure;

        return $celsius + 0.33 * $vapourPressure - 0.70 * $windMs - 4.00;
    }

    /**
     * As `celsius`, but treats an absent wind reading as calm so stations
     * without an anemometer still produce a value.
     *
     * The wind term is subtractive, so this is an **upper bound**: the true
     * value is lower by 0.70 × the unreported wind speed in m/s. Callers must
     * label such values as approximate — use `windAssumedCalm` to detect them.
     */
    public static function estimateCelsius(?float $celsius, ?float $humidityPct, ?float $windMs): ?float
    {
        return self::celsius($celsius, $humidityPct, $windMs ?? 0.0);
    }

    /**
     * True when `estimateCelsius` produced a value only by assuming calm —
     * that is, temperature and humidity are present but wind is not.
     *
     * A measured zero is real data, so it does not count as an assumption.
     */
    public static function windAssumedCalm(?float $celsius, ?float $humidityPct, ?float $windMs): bool
    {
        return $windMs === null && $celsius !== null && $humidityPct !== null;
    }
}
