<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Observation\Meteorology\ApparentTemperature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApparentTemperatureTest extends TestCase
{
    /**
     * Reference points chosen so the expected value is verifiable by hand.
     *
     * At RH = 0 the vapour term vanishes (e = 0), leaving AT = T - 0.70v - 4.
     * At T = 0 the Magnus exponent is zero, so es = 6.105 hPa exactly.
     */
    #[DataProvider('referenceCases')]
    public function testMatchesHandCalculatedReferencePoints(
        float $celsius,
        float $humidityPct,
        float $windMs,
        float $expected,
    ): void {
        self::assertEqualsWithDelta(
            $expected,
            ApparentTemperature::celsius($celsius, $humidityPct, $windMs),
            0.0001,
        );
    }

    /**
     * @return iterable<string, array{0:float,1:float,2:float,3:float}>
     */
    public static function referenceCases(): iterable
    {
        // e = 0 → AT = 20 - 0 - 4
        yield 'dry and calm'      => [20.0, 0.0,   0.0, 16.0];
        // e = 0 → AT = 20 - 3.5 - 4
        yield 'dry and windy'     => [20.0, 0.0,   5.0, 12.5];
        // es = 6.105 → e = 6.105 → AT = 0 + 2.01465 - 0 - 4
        yield 'saturated at zero' => [0.0,  100.0, 0.0, -1.98535];
        // es = 6.105 → e = 3.0525 → AT = 0 + 1.007325 - 0 - 4
        yield 'half humid at zero' => [0.0, 50.0,  0.0, -2.992675];
        // es = 6.105 → e = 6.105 → AT = 0 + 2.01465 - 1.4 - 4
        yield 'saturated and windy' => [0.0, 100.0, 2.0, -3.38535];
    }

    #[DataProvider('missingInputCases')]
    public function testReturnsNullWhenAnyRequiredInputIsMissing(
        ?float $celsius,
        ?float $humidityPct,
        ?float $windMs,
    ): void {
        self::assertNull(ApparentTemperature::celsius($celsius, $humidityPct, $windMs));
    }

    /**
     * @return iterable<string, array{0:?float,1:?float,2:?float}>
     */
    public static function missingInputCases(): iterable
    {
        yield 'no temperature' => [null, 60.0, 3.0];
        yield 'no humidity'    => [18.0, null, 3.0];
        yield 'no wind'        => [18.0, 60.0, null];
        yield 'nothing at all' => [null, null, null];
    }

    public function testTreatsZeroReadingsAsRealValuesNotMissingData(): void
    {
        self::assertNotNull(ApparentTemperature::celsius(20.0, 0.0, 0.0));
    }

    /**
     * IPMA stations without an anemometer still report temperature and
     * humidity; `estimateCelsius` fills the wind term with calm so those
     * stations show a value, which callers must label as approximate.
     */
    public function testEstimateTreatsAbsentWindAsCalm(): void
    {
        self::assertEqualsWithDelta(
            ApparentTemperature::celsius(20.0, 0.0, 0.0),
            ApparentTemperature::estimateCelsius(20.0, 0.0, null),
            0.0001,
        );
    }

    public function testEstimateMatchesStrictCalculationWhenWindIsReported(): void
    {
        self::assertEqualsWithDelta(
            ApparentTemperature::celsius(24.0, 65.0, 3.5),
            ApparentTemperature::estimateCelsius(24.0, 65.0, 3.5),
            0.0001,
        );
    }

    public function testEstimateStillNeedsTemperatureAndHumidity(): void
    {
        self::assertNull(ApparentTemperature::estimateCelsius(null, 65.0, null));
        self::assertNull(ApparentTemperature::estimateCelsius(24.0, null, null));
    }

    /**
     * Assuming calm can only raise the result, since the wind term is
     * subtractive — so the estimate is an upper bound on the true value.
     */
    public function testAssumingCalmOverstatesTheValue(): void
    {
        $assumed = ApparentTemperature::estimateCelsius(28.0, 60.0, null);
        $actual  = ApparentTemperature::celsius(28.0, 60.0, 5.0);

        self::assertNotNull($assumed);
        self::assertNotNull($actual);
        self::assertGreaterThan($actual, $assumed);
    }

    #[DataProvider('windAssumedCases')]
    public function testWindAssumedCalmFlag(
        ?float $celsius,
        ?float $humidityPct,
        ?float $windMs,
        bool $expected,
    ): void {
        self::assertSame($expected, ApparentTemperature::windAssumedCalm($celsius, $humidityPct, $windMs));
    }

    /**
     * @return iterable<string, array{0:?float,1:?float,2:?float,3:bool}>
     */
    public static function windAssumedCases(): iterable
    {
        yield 'wind absent, others present' => [20.0, 50.0, null, true];
        yield 'wind reported'               => [20.0, 50.0, 3.0,  false];
        // A measured calm is real data, not an assumption.
        yield 'wind measured as calm'       => [20.0, 50.0, 0.0,  false];
        // Nothing was produced at all, so nothing was assumed.
        yield 'temperature also absent'     => [null, 50.0, null, false];
        yield 'humidity also absent'        => [20.0, null, null, false];
    }

    public function testHigherHumidityFeelsWarmer(): void
    {
        $dry   = ApparentTemperature::celsius(28.0, 30.0, 2.0);
        $humid = ApparentTemperature::celsius(28.0, 80.0, 2.0);

        self::assertNotNull($dry);
        self::assertNotNull($humid);
        self::assertGreaterThan($dry, $humid);
    }

    public function testStrongerWindFeelsCooler(): void
    {
        $calm  = ApparentTemperature::celsius(28.0, 60.0, 0.0);
        $windy = ApparentTemperature::celsius(28.0, 60.0, 8.0);

        self::assertNotNull($calm);
        self::assertNotNull($windy);
        self::assertLessThan($calm, $windy);
    }
}
