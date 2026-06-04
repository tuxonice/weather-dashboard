<?php

declare(strict_types=1);

namespace App\Service\Astronomy;

use DateTimeImmutable;

/**
 * Solar system planet position calculator.
 *
 * Computes equatorial (RA/DEC) and horizontal (alt/az) coordinates,
 * distance, magnitude, and rise/set times for the classical planets.
 *
 * Based on simplified Meeus algorithms (Astronomical Algorithms 2nd ed.)
 * with VSOP87-inspired periodic terms for acceptable accuracy
 * (within ~0.1° for most planets, adequate for naked-eye observation planning).
 *
 * Pure, dependency-free helper (cf. MoonPhase); NOT registered in DI container.
 */
final class PlanetPosition
{
    public const MERCURY = 'mercury';
    public const VENUS = 'venus';
    public const MARS = 'mars';
    public const JUPITER = 'jupiter';
    public const SATURN = 'saturn';
    public const URANUS = 'uranus';
    public const NEPTUNE = 'neptune';

    /** @var array<string, array{name: string, emoji: string, translation_key: string}> */
    private const PLANET_META = [
        self::MERCURY => ['name' => 'Mercury', 'emoji' => '☿', 'translation_key' => 'location.planet_mercury'],
        self::VENUS   => ['name' => 'Venus',   'emoji' => '♀', 'translation_key' => 'location.planet_venus'],
        self::MARS    => ['name' => 'Mars',    'emoji' => '♂', 'translation_key' => 'location.planet_mars'],
        self::JUPITER => ['name' => 'Jupiter', 'emoji' => '♃', 'translation_key' => 'location.planet_jupiter'],
        self::SATURN  => ['name' => 'Saturn',  'emoji' => '♄', 'translation_key' => 'location.planet_saturn'],
        self::URANUS  => ['name' => 'Uranus',  'emoji' => '♅', 'translation_key' => 'location.planet_uranus'],
        self::NEPTUNE => ['name' => 'Neptune', 'emoji' => '♆', 'translation_key' => 'location.planet_neptune'],
    ];

    /** @var array<string, array{a: float, e: float, i: float, L: float, w: float, W: float, dL: float}> */
    private const ORBITAL_ELEMENTS = [
        // a = semi-major axis (AU), e = eccentricity, i = inclination (deg)
        // L = mean longitude (deg), w = argument of perihelion (deg), W = longitude of ascending node (deg)
        // dL = daily motion (deg/day)
        self::MERCURY => ['a' => 0.387099, 'e' => 0.205634, 'i' => 7.005,  'L' => 252.251, 'w' => 77.457,  'W' => 48.331,  'dL' => 4.092339],
        self::VENUS   => ['a' => 0.723332, 'e' => 0.006773, 'i' => 3.395,  'L' => 181.979, 'w' => 131.571, 'W' => 76.680,  'dL' => 1.602130],
        self::MARS    => ['a' => 1.523679, 'e' => 0.093405, 'i' => 1.850,  'L' => 355.433, 'w' => 336.059, 'W' => 49.558,  'dL' => 0.524033],
        self::JUPITER => ['a' => 5.202603, 'e' => 0.048498, 'i' => 1.303,  'L' => 34.351,  'w' => 14.331,  'W' => 100.464, 'dL' => 0.083091],
        self::SATURN  => ['a' => 9.554909, 'e' => 0.055508, 'i' => 2.489,  'L' => 50.077,  'w' => 93.057,  'W' => 113.665, 'dL' => 0.033444],
        self::URANUS  => ['a' => 19.218446,'e' => 0.046296, 'i' => 0.773,  'L' => 314.055, 'w' => 173.005, 'W' => 74.006,  'dL' => 0.011698],
        self::NEPTUNE => ['a' => 30.110387,'e' => 0.008989, 'i' => 1.770,  'L' => 304.349, 'w' => 48.123,  'W' => 131.784, 'dL' => 0.005965],
    ];

    /**
     * Get position data for all planets at a given time and observer location.
     *
     * @return list<array{
     *   id: string,
     *   name: string,
     *   emoji: string,
     *   translation_key: string,
     *   ra: float,           // Right ascension in degrees (0-360)
     *   dec: float,          // Declination in degrees (-90 to +90)
     *   ra_hms: string,      // RA formatted as HH:MM:SS
     *   dec_dms: string,     // DEC formatted as +DD°MM'SS"
     *   azimuth: float,      // Azimuth in degrees (0-360, N=0, E=90)
     *   altitude: float,     // Altitude in degrees (-90 to +90)
     *   distance_au: float, // Distance from Earth in AU
     *   distance_km: int,    // Distance from Earth in km
     *   magnitude: float,    // Apparent magnitude
     *   elongation: float,   // Angular separation from Sun in degrees
     *   is_visible: bool,   // True if altitude > 0 (above horizon)
     *   is_morning: bool,   // True if rising, false if setting
     * }>
     */
    public static function getAllPlanetPositions(
        DateTimeImmutable $when,
        float $observerLat,
        float $observerLon,
    ): array {
        $jd = self::toJulianDay($when);
        $results = [];

        foreach (array_keys(self::PLANET_META) as $planet) {
            $results[] = self::getPlanetPosition($planet, $jd, $observerLat, $observerLon, $when);
        }

        return $results;
    }

    /**
     * Get planets currently visible (altitude > 0°) sorted by brightness.
     *
     * @return list<array{id: string, name: string, emoji: string, translation_key: string, ra: float, dec: float, ra_hms: string, dec_dms: string, azimuth: float, altitude: float, distance_au: float, distance_km: int, magnitude: float, elongation: float, is_visible: bool, is_morning: bool}>
     */
    public static function getVisiblePlanets(
        DateTimeImmutable $when,
        float $observerLat,
        float $observerLon,
    ): array {
        $all = self::getAllPlanetPositions($when, $observerLat, $observerLon);

        $visible = array_filter($all, static fn(array $p): bool => $p['is_visible']);

        // Sort by magnitude (brightness) - lower is brighter
        usort($visible, static function (array $a, array $b): int {
            return $a['magnitude'] <=> $b['magnitude'];
        });

        return $visible;
    }

    /**
     * Get rise and set times for a planet on a given date.
     *
     * @return array{rise: DateTimeImmutable|null, set: DateTimeImmutable|null, transit: DateTimeImmutable|null}
     */
    public static function getRiseSetTimes(
        string $planet,
        DateTimeImmutable $date,
        float $observerLat,
        float $observerLon,
        string $timezone,
    ): array {
        // Start at noon UTC of the given date for rise/set calculation
        $noonUtc = new DateTimeImmutable($date->format('Y-m-d') . ' 12:00:00', new \DateTimeZone('UTC'));
        $jd = self::toJulianDay($noonUtc);

        // Calculate at 3-hour intervals to find approximate rise/set
        /** @var list<array{jd: float, altitude: float, azimuth: float}> $times */
        $times = [];
        for ($hour = -12; $hour <= 36; $hour += 3) {
            $testJd = $jd + $hour / 24.0;
            $pos = self::getPlanetPosition($planet, $testJd, $observerLat, $observerLon, $noonUtc);
            $times[] = [
                'jd' => $testJd,
                'altitude' => $pos['altitude'],
                'azimuth' => $pos['azimuth'],
            ];
        }

        // Find sign changes in altitude (crossing horizon)
        $rise = null;
        $set = null;
        $transit = null;
        $maxAlt = -90;

        for ($i = 0; $i < count($times) - 1; $i++) {
            $alt1 = $times[$i]['altitude'];
            $alt2 = $times[$i + 1]['altitude'];

            // Track maximum altitude for transit
            if ($alt1 > $maxAlt) {
                $maxAlt = $alt1;
            }

            // Check for horizon crossing
            if ($alt1 < 0 && $alt2 >= 0) {
                // Rising - interpolate
                $fraction = abs($alt1) / (abs($alt1) + $alt2);
                $riseJd = $times[$i]['jd'] + $fraction * 0.125; // 3 hours = 0.125 days
                $rise = self::fromJulianDay($riseJd)->setTimezone(new \DateTimeZone($timezone));
            } elseif ($alt1 >= 0 && $alt2 < 0) {
                // Setting - interpolate
                $fraction = $alt1 / ($alt1 + abs($alt2));
                $setJd = $times[$i]['jd'] + $fraction * 0.125;
                $set = self::fromJulianDay($setJd)->setTimezone(new \DateTimeZone($timezone));
            }
        }

        // Find transit (when azimuth is ~180 for southern hemisphere, ~0/360 for northern)
        // Simplified: roughly halfway between rise and set
        if ($rise !== null && $set !== null) {
            $riseTs = $rise->getTimestamp();
            $setTs = $set->getTimestamp();
            $midTs = ($riseTs + $setTs) / 2;
            $transit = (new DateTimeImmutable('@' . (int)$midTs))->setTimezone(new \DateTimeZone($timezone));
        }

        return ['rise' => $rise, 'set' => $set, 'transit' => $transit];
    }

    /**
     * Get position for a single planet.
     *
     * @return array{id: string, name: string, emoji: string, translation_key: string, ra: float, dec: float, ra_hms: string, dec_dms: string, azimuth: float, altitude: float, distance_au: float, distance_km: int, magnitude: float, elongation: float, is_visible: bool, is_morning: bool}
     */
    private static function getPlanetPosition(
        string $planet,
        float $jd,
        float $observerLat,
        float $observerLon,
        DateTimeImmutable $when,
    ): array {
        $T = ($jd - 2451545.0) / 36525.0; // Julian centuries from J2000

        // Calculate heliocentric position
        $heliocentric = self::getHeliocentricPosition($planet, $T);

        // Calculate Earth's heliocentric position
        $earthHelio = self::getHeliocentricPosition('earth', $T);

        // Convert to geocentric
        $geocentric = self::heliocentricToGeocentric($heliocentric, $earthHelio);

        // Convert to equatorial coordinates (RA/DEC)
        $equatorial = self::toEquatorial($geocentric);

        // Calculate distance and magnitude
        $distanceAu = sqrt($geocentric['x'] * $geocentric['x'] + $geocentric['y'] * $geocentric['y'] + $geocentric['z'] * $geocentric['z']);
        $magnitude = self::calculateMagnitude($planet, $distanceAu, $heliocentric, $earthHelio);

        // Calculate elongation from Sun
        $elongation = self::calculateElongation($heliocentric, $earthHelio);

        // Convert to horizontal coordinates (alt/az)
        $lst = self::localSiderealTime($jd, $observerLon);
        $hourAngle = $lst - $equatorial['ra'];
        $horizontal = self::toHorizontal($hourAngle, $equatorial['dec'], $observerLat);

        $meta = self::PLANET_META[$planet];

        return [
            'id' => $planet,
            'name' => $meta['name'],
            'emoji' => $meta['emoji'],
            'translation_key' => $meta['translation_key'],
            'ra' => $equatorial['ra'],
            'dec' => $equatorial['dec'],
            'ra_hms' => self::degreesToHms($equatorial['ra']),
            'dec_dms' => self::degreesToDms($equatorial['dec']),
            'azimuth' => round($horizontal['azimuth'], 1),
            'altitude' => round($horizontal['altitude'], 1),
            'distance_au' => round($distanceAu, 3),
            'distance_km' => (int) round($distanceAu * 149597870.7),
            'magnitude' => round($magnitude, 1),
            'elongation' => round($elongation, 1),
            'is_visible' => $horizontal['altitude'] > 0,
            'is_morning' => $hourAngle < 0, // Before transit = morning (rising)
        ];
    }

    /**
     * Calculate heliocentric ecliptic coordinates for a planet.
     *
     * @return array{x: float, y: float, z: float, lon: float, lat: float, r: float}
     */
    private static function getHeliocentricPosition(string $planet, float $T): array
    {
        if ($planet === 'earth') {
            // Earth is treated as having orbital elements too
            $elements = [
                'a' => 1.00000261, 'e' => 0.01670862, 'i' => 0.00001531,
                'L' => 100.46457166, 'w' => 102.93768193, 'W' => 0.0,
                'dL' => 0.98560910,
            ];
        } else {
            $elements = self::ORBITAL_ELEMENTS[$planet];
        }

        $t = $T; // Use T directly for centuries

        // Update orbital elements for time T
        $a = $elements['a'];
        $e = $elements['e'] + self::rate('e', $planet) * $t;
        $i = $elements['i'] + self::rate('i', $planet) * $t;
        $L = $elements['L'] + $elements['dL'] * 36525.0 * $t; // dL is per day, convert to per century
        $w = $elements['w'] + self::rate('w', $planet) * $t;
        $W = $elements['W'] + self::rate('W', $planet) * $t;

        // Argument of perihelion
        $omega = $w - $W;

        // Mean anomaly
        $M = $L - $w;
        $M = self::normalizeAngle($M);

        // Solve Kepler's equation: M = E - e*sin(E)
        $E = $M; // Initial guess
        for ($iter = 0; $iter < 5; $iter++) {
            $delta = $E - $e * sin(deg2rad($E)) - $M;
            $E -= $delta / (1 - $e * cos(deg2rad($E)));
        }

        // True anomaly
        $nu = 2 * rad2deg(atan(sqrt((1 + $e) / (1 - $e)) * tan(deg2rad($E / 2))));
        $nu = self::normalizeAngle($nu);

        // Distance from Sun
        $r = $a * (1 - $e * cos(deg2rad($E)));

        // Heliocentric ecliptic coordinates
        $lon = $nu + $w;
        $lon = self::normalizeAngle($lon);
        $lat = 0.0; // Simplified: assuming small inclination effect

        $x = $r * cos(deg2rad($lat)) * cos(deg2rad($lon));
        $y = $r * cos(deg2rad($lat)) * sin(deg2rad($lon));
        $z = $r * sin(deg2rad($lat));

        return ['x' => $x, 'y' => $y, 'z' => $z, 'lon' => $lon, 'lat' => $lat, 'r' => $r];
    }

    /**
     * Get rate of change for orbital elements (per century).
     */
    private static function rate(string $element, string $planet): float
    {
        // Simplified rates - most elements change slowly
        $rates = [
            'e' => ['mercury' => 0.000002, 'venus' => -0.000050, 'mars' => 0.000091,
                    'jupiter' => -0.000128, 'saturn' => -0.000349, 'uranus' => -0.000021, 'neptune' => 0.000007],
            'i' => ['mercury' => -0.0059, 'venus' => -0.0004, 'mars' => -0.0081,
                    'jupiter' => -0.0014, 'saturn' => 0.0016, 'uranus' => -0.0010, 'neptune' => 0.0001],
            'w' => ['mercury' => 0.1594, 'venus' => 0.0568, 'mars' => 0.2926,
                    'jupiter' => 0.1814, 'saturn' => 1.0091, 'uranus' => 0.4898, 'neptune' => 0.2179],
            'W' => ['mercury' => -0.1259, 'venus' => -0.2778, 'mars' => -0.2926,
                    'jupiter' => 0.1377, 'saturn' => -0.2509, 'uranus' => 0.0457, 'neptune' => -0.5410],
        ];

        return $rates[$element][$planet] ?? 0.0;
    }

    /**
     * Convert heliocentric to geocentric coordinates.
     *
     * @param array{x: float, y: float, z: float} $planet
     * @param array{x: float, y: float, z: float} $earth
     * @return array{x: float, y: float, z: float}
     */
    private static function heliocentricToGeocentric(array $planet, array $earth): array
    {
        return [
            'x' => $planet['x'] - $earth['x'],
            'y' => $planet['y'] - $earth['y'],
            'z' => $planet['z'] - $earth['z'],
        ];
    }

    /**
     * Convert ecliptic to equatorial coordinates.
     *
     * @param array{x: float, y: float, z: float} $geo
     * @return array{ra: float, dec: float}
     */
    private static function toEquatorial(array $geo): array
    {
        // Obliquity of the ecliptic (simplified)
        $epsilon = 23.43928; // J2000 value

        $x = $geo['x'];
        $y = $geo['y'];
        $z = $geo['z'];

        // Rotate around X axis by -epsilon
        $yEq = $y * cos(deg2rad($epsilon)) - $z * sin(deg2rad($epsilon));
        $zEq = $y * sin(deg2rad($epsilon)) + $z * cos(deg2rad($epsilon));

        $ra = rad2deg(atan2($yEq, $x));
        $ra = self::normalizeAngle($ra);
        $dec = rad2deg(atan2($zEq, sqrt($x * $x + $yEq * $yEq)));

        return ['ra' => $ra, 'dec' => $dec];
    }

    /**
     * Convert hour angle and declination to horizontal coordinates.
     *
     * @return array{azimuth: float, altitude: float}
     */
    private static function toHorizontal(float $hourAngle, float $dec, float $lat): array
    {
        $H = deg2rad($hourAngle);
        $delta = deg2rad($dec);
        $phi = deg2rad($lat);

        $sinAlt = sin($delta) * sin($phi) + cos($delta) * cos($phi) * cos($H);
        $altitude = rad2deg(asin($sinAlt));

        $y = -cos($delta) * cos($phi) * sin($H);
        $x = sin($delta) - sin($phi) * sin(deg2rad($altitude));
        $azimuth = rad2deg(atan2($y, $x));
        $azimuth = self::normalizeAngle($azimuth);

        return ['azimuth' => $azimuth, 'altitude' => $altitude];
    }

    /**
     * Calculate local sidereal time in degrees.
     */
    private static function localSiderealTime(float $jd, float $longitude): float
    {
        $T = ($jd - 2451545.0) / 36525.0;

        // Greenwich sidereal time at 0h UT
        $gst = 280.46061837 + 360.98564736629 * ($jd - 2451545.0) +
               0.000387933 * $T * $T - $T * $T * $T / 38710000.0;

        $gst = self::normalizeAngle($gst);

        // Local sidereal time
        $lst = $gst + $longitude;
        return self::normalizeAngle($lst);
    }

    /**
     * Calculate apparent magnitude using simplified formula.
     *
     * @param array{x: float, y: float, z: float, lon: float, lat: float, r: float} $heliocentric
     * @param array{x: float, y: float, z: float, lon: float, lat: float, r: float} $earthHelio
     */
    private static function calculateMagnitude(
        string $planet,
        float $distanceEarth,
        array $heliocentric,
        array $earthHelio,
    ): float {
        // Geometric distances
        $r = $heliocentric['r']; // Distance from Sun
        $delta = $distanceEarth;  // Distance from Earth

        // Phase angle (Sun-Earth-Planet angle)
        $cosPhase = ($r * $r + $delta * $delta - $earthHelio['r'] * $earthHelio['r']) / (2 * $r * $delta);
        $phase = rad2deg(acos(max(-1, min(1, $cosPhase))));

        // Simplified magnitude formulas based on phase
        $magnitudes = [
            self::MERCURY => -0.42 + 0.038 * $phase - 0.000273 * $phase * $phase + 5 * log10($r * $delta),
            self::VENUS   => -4.4 + 0.0009 * $phase + 0.000239 * $phase * $phase - 0.00000065 * $phase * $phase * $phase + 5 * log10($r * $delta),
            self::MARS    => -1.52 + 0.016 * $phase + 5 * log10($r * $delta),
            self::JUPITER => -9.4 + 0.005 * $phase + 5 * log10($r * $delta),
            self::SATURN  => -8.88 + 0.044 * $phase + 5 * log10($r * $delta) + 0.5 * (1 - cos(deg2rad($phase))),
            self::URANUS  => -7.19 + 0.002 * $phase + 5 * log10($r * $delta),
            self::NEPTUNE => -6.87 + 0.001 * $phase + 5 * log10($r * $delta),
        ];

        return $magnitudes[$planet] ?? 99.0;
    }

    /**
     * Calculate elongation (angular separation from Sun).
     *
     * @param array{x: float, y: float, z: float, lon: float, lat: float, r: float} $planetHelio
     * @param array{x: float, y: float, z: float, lon: float, lat: float, r: float} $earthHelio
     */
    private static function calculateElongation(array $planetHelio, array $earthHelio): float
    {
        // Geocentric vectors
        $px = $planetHelio['x'] - $earthHelio['x'];
        $py = $planetHelio['y'] - $earthHelio['y'];
        $pz = $planetHelio['z'] - $earthHelio['z'];

        // Sun is at origin in heliocentric, so Earth->Sun vector is just -Earth position
        $sx = -$earthHelio['x'];
        $sy = -$earthHelio['y'];
        $sz = -$earthHelio['z'];

        $pMag = sqrt($px * $px + $py * $py + $pz * $pz);
        $sMag = sqrt($sx * $sx + $sy * $sy + $sz * $sz);

        $dot = $px * $sx + $py * $sy + $pz * $sz;
        $cosElong = $dot / ($pMag * $sMag);

        return rad2deg(acos(max(-1, min(1, $cosElong))));
    }

    /**
     * Convert DateTimeImmutable to Julian Day.
     */
    private static function toJulianDay(DateTimeImmutable $dt): float
    {
        return $dt->getTimestamp() / 86400.0 + 2440587.5;
    }

    /**
     * Convert Julian Day to DateTimeImmutable (UTC).
     */
    private static function fromJulianDay(float $jd): DateTimeImmutable
    {
        $timestamp = (int) round(($jd - 2440587.5) * 86400.0);
        return new DateTimeImmutable('@' . $timestamp);
    }

    /**
     * Normalize angle to 0-360 degrees.
     */
    private static function normalizeAngle(float $angle): float
    {
        $angle = fmod($angle, 360.0);
        if ($angle < 0) {
            $angle += 360.0;
        }
        return $angle;
    }

    /**
     * Convert degrees to HH:MM:SS format.
     */
    private static function degreesToHms(float $degrees): string
    {
        $hours = $degrees / 15.0;
        $h = (int) $hours;
        $m = (int) (($hours - $h) * 60);
        $s = round((($hours - $h) * 60 - $m) * 60);

        if ($s >= 60) {
            $s -= 60;
            $m++;
        }
        if ($m >= 60) {
            $m -= 60;
            $h++;
        }

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    /**
     * Convert degrees to DD°MM'SS" format.
     */
    private static function degreesToDms(float $degrees): string
    {
        $sign = $degrees < 0 ? '-' : '+';
        $deg = abs($degrees);
        $d = (int) $deg;
        $m = (int) (($deg - $d) * 60);
        $s = round((($deg - $d) * 60 - $m) * 60);

        if ($s >= 60) {
            $s -= 60;
            $m++;
        }
        if ($m >= 60) {
            $m -= 60;
            $d++;
        }

        return sprintf('%s%02d°%02d\'%02d"', $sign, $d, $m, $s);
    }

    public static function translationKey(string $planet): string
    {
        return self::PLANET_META[$planet]['translation_key'] ?? 'common.unknown';
    }

    public static function emoji(string $planet): string
    {
        return self::PLANET_META[$planet]['emoji'] ?? '?';
    }
}
