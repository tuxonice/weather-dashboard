<?php

declare(strict_types=1);

namespace App\Tests\Framework;

use App\Framework\EndpointHashIndex;
use PHPUnit\Framework\TestCase;
use Tlab\IpmaApi\Endpoints;

final class EndpointHashIndexTest extends TestCase
{
    public function testResolvesPlaceholderFreeEndpoints(): void
    {
        self::assertSame(
            Endpoints::WEATHER_WARNINGS,
            EndpointHashIndex::resolve(self::cacheKey(Endpoints::WEATHER_WARNINGS)),
        );
        self::assertSame(
            Endpoints::WEATHER_STATION_OBSERVATION,
            EndpointHashIndex::resolve(self::cacheKey(Endpoints::WEATHER_STATION_OBSERVATION)),
        );
    }

    public function testResolvesEndpointsWhosePlaceholderIsBackedByAnEnum(): void
    {
        $seismic = str_replace('{idArea}', '7', Endpoints::SEISMIC_INFORMATION);
        $seaState = str_replace('{idDay}', '2', Endpoints::SEA_STATE_FORECAST);
        $fireRisk = str_replace('{idDay}', '1', Endpoints::FIRE_RISK_FORECAST);

        self::assertSame($seismic, EndpointHashIndex::resolve(self::cacheKey($seismic)));
        self::assertSame($seaState, EndpointHashIndex::resolve(self::cacheKey($seaState)));
        self::assertSame($fireRisk, EndpointHashIndex::resolve(self::cacheKey($fireRisk)));
    }

    public function testReturnsNullForEndpointsItCannotEnumerate(): void
    {
        $forecast = str_replace('{globalIdLocal}', '1010500', Endpoints::DAILY_WEATHER_FORECAST_BY_LOCATION);

        self::assertNull(EndpointHashIndex::resolve(self::cacheKey($forecast)));
    }

    public function testReturnsNullForKeysThatAreNotIpmaResponses(): void
    {
        self::assertNull(EndpointHashIndex::resolve('some.other.cache.key'));
    }

    private static function cacheKey(string $endpoint): string
    {
        return 'ipma_api.' . hash('sha256', $endpoint);
    }
}
