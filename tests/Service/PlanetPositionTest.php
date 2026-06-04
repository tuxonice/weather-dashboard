<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Astronomy\PlanetPosition;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PlanetPosition calculator.
 *
 * These tests verify that the calculator produces sensible results
 * within expected astronomical ranges. Exact ephemeris comparisons
 * are not included due to the simplified nature of the algorithm.
 */
final class PlanetPositionTest extends TestCase
{
    private const UTC_FMT = 'Y-m-d H:i:s';

    private static function utc(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso, new DateTimeZone('UTC'));
    }

    public function testReturnsAllSevenPlanets(): void
    {
        $when = self::utc('2026-06-01 12:00:00');
        $positions = PlanetPosition::getAllPlanetPositions($when, 38.7, -9.1);

        self::assertCount(7, $positions);

        $ids = array_column($positions, 'id');
        self::assertContains(PlanetPosition::MERCURY, $ids);
        self::assertContains(PlanetPosition::VENUS, $ids);
        self::assertContains(PlanetPosition::MARS, $ids);
        self::assertContains(PlanetPosition::JUPITER, $ids);
        self::assertContains(PlanetPosition::SATURN, $ids);
        self::assertContains(PlanetPosition::URANUS, $ids);
        self::assertContains(PlanetPosition::NEPTUNE, $ids);
    }

    public function testCoordinatesAreInValidRanges(): void
    {
        $when = self::utc('2026-06-01 12:00:00');
        $positions = PlanetPosition::getAllPlanetPositions($when, 38.7, -9.1);

        foreach ($positions as $p) {
            // RA should be 0-360 degrees
            self::assertGreaterThanOrEqual(0.0, $p['ra']);
            self::assertLessThanOrEqual(360.0, $p['ra']);

            // DEC should be -90 to +90
            self::assertGreaterThanOrEqual(-90.0, $p['dec']);
            self::assertLessThanOrEqual(90.0, $p['dec']);

            // Azimuth should be 0-360
            self::assertGreaterThanOrEqual(0.0, $p['azimuth']);
            self::assertLessThanOrEqual(360.0, $p['azimuth']);

            // Altitude should be -90 to +90
            self::assertGreaterThanOrEqual(-90.0, $p['altitude']);
            self::assertLessThanOrEqual(90.0, $p['altitude']);

            // Distance should be reasonable (0.1 to 50 AU)
            self::assertGreaterThan(0.1, $p['distance_au']);
            self::assertLessThan(50.0, $p['distance_au']);

            // Distance in km should be positive and reasonable
            self::assertGreaterThan(1000000, $p['distance_km']); // > 1 million km
            self::assertLessThan(8000000000, $p['distance_km']); // < 8 billion km (Neptune max)
        }
    }

    public function testVisiblePlanetsAreAboveHorizon(): void
    {
        $when = self::utc('2026-06-01 12:00:00');
        $visible = PlanetPosition::getVisiblePlanets($when, 38.7, -9.1);

        foreach ($visible as $p) {
            self::assertTrue($p['is_visible']);
            self::assertGreaterThan(0.0, $p['altitude']);
        }
    }

    public function testMagnitudesAreInPlausibleRange(): void
    {
        $when = self::utc('2026-06-01 12:00:00');
        $positions = PlanetPosition::getAllPlanetPositions($when, 38.7, -9.1);

        foreach ($positions as $p) {
            // All planets should be brighter than magnitude 15 (even faint ones)
            self::assertLessThan(15.0, $p['magnitude']);

            // Bright planets should be negative or small positive
            if ($p['id'] === PlanetPosition::VENUS || $p['id'] === PlanetPosition::JUPITER) {
                // Venus and Jupiter are typically very bright
                self::assertLessThan(0.0, $p['magnitude']);
            }
        }
    }

    public function testElongationsAreWithinPhysicalLimits(): void
    {
        $when = self::utc('2026-06-01 12:00:00');
        $positions = PlanetPosition::getAllPlanetPositions($when, 38.7, -9.1);

        foreach ($positions as $p) {
            // Elongation from Sun should be 0-180 degrees
            self::assertGreaterThanOrEqual(0.0, $p['elongation']);
            self::assertLessThanOrEqual(180.0, $p['elongation']);

            // Inner planets (Mercury, Venus) should have elongation < 48° max
            if ($p['id'] === PlanetPosition::MERCURY) {
                self::assertLessThan(48.0, $p['elongation']);
            }
            if ($p['id'] === PlanetPosition::VENUS) {
                self::assertLessThan(48.0, $p['elongation']);
            }
        }
    }

    public function testRiseSetTimesAreOrdered(): void
    {
        $date = self::utc('2026-06-01 00:00:00');
        $times = PlanetPosition::getRiseSetTimes(PlanetPosition::MARS, $date, 38.7, -9.1, 'Europe/Lisbon');

        // Either both are null (circumpolar case) or rise < transit < set
        if ($times['rise'] !== null && $times['set'] !== null) {
            self::assertLessThan($times['transit']->getTimestamp(), $times['rise']->getTimestamp());
            self::assertLessThan($times['set']->getTimestamp(), $times['transit']->getTimestamp());
        }
    }

    public function testTranslationKeyAndEmojiMapping(): void
    {
        self::assertSame('location.planet_mercury', PlanetPosition::translationKey(PlanetPosition::MERCURY));
        self::assertSame('location.planet_venus', PlanetPosition::translationKey(PlanetPosition::VENUS));
        self::assertSame('location.planet_mars', PlanetPosition::translationKey(PlanetPosition::MARS));
        self::assertSame('location.planet_jupiter', PlanetPosition::translationKey(PlanetPosition::JUPITER));
        self::assertSame('location.planet_saturn', PlanetPosition::translationKey(PlanetPosition::SATURN));
        self::assertSame('location.planet_uranus', PlanetPosition::translationKey(PlanetPosition::URANUS));
        self::assertSame('location.planet_neptune', PlanetPosition::translationKey(PlanetPosition::NEPTUNE));

        self::assertSame('☿', PlanetPosition::emoji(PlanetPosition::MERCURY));
        self::assertSame('♀', PlanetPosition::emoji(PlanetPosition::VENUS));
        self::assertSame('♂', PlanetPosition::emoji(PlanetPosition::MARS));
    }

    public function testFormattersProduceValidOutput(): void
    {
        $when = self::utc('2026-06-01 12:00:00');
        $positions = PlanetPosition::getAllPlanetPositions($when, 38.7, -9.1);

        foreach ($positions as $p) {
            // RA should be in HH:MM:SS format
            self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $p['ra_hms']);

            // DEC should be in +DD°MM'SS" format
            self::assertMatchesRegularExpression('/^[+-]\d{2}°\d{2}\'\d{2}"$/', $p['dec_dms']);
        }
    }

    public function testPlanetsAtDifferentTimesHaveDifferentPositions(): void
    {
        $lat = 38.7;
        $lon = -9.1;

        $morning = self::utc('2026-06-01 06:00:00');
        $evening = self::utc('2026-06-01 20:00:00');

        $morningPos = PlanetPosition::getAllPlanetPositions($morning, $lat, $lon);
        $eveningPos = PlanetPosition::getAllPlanetPositions($evening, $lat, $lon);

        // At least some planets should have changed altitude significantly
        $changes = 0;
        for ($i = 0; $i < count($morningPos); $i++) {
            $altDiff = abs($morningPos[$i]['altitude'] - $eveningPos[$i]['altitude']);
            if ($altDiff > 5.0) {
                $changes++;
            }
        }

        self::assertGreaterThan(0, $changes, 'At least some planets should change position between morning and evening');
    }

    public function testVisiblePlanetsSortedByBrightness(): void
    {
        $when = self::utc('2026-06-01 12:00:00');
        $visible = PlanetPosition::getVisiblePlanets($when, 38.7, -9.1);

        if (count($visible) >= 2) {
            // Check that array is sorted by magnitude (ascending = brighter first)
            for ($i = 0; $i < count($visible) - 1; $i++) {
                self::assertLessThanOrEqual(
                    $visible[$i + 1]['magnitude'],
                    $visible[$i]['magnitude'],
                    'Visible planets should be sorted by brightness'
                );
            }
        }
    }
}
