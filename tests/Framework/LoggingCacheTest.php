<?php

declare(strict_types=1);

namespace App\Tests\Framework;

use App\Framework\LoggingCache;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Tlab\IpmaApi\Endpoints;

final class LoggingCacheTest extends TestCase
{
    public function testStoringAnIpmaResponseLogsOneUpstreamRequest(): void
    {
        $logger = new RecordingLogger();
        $cache = new LoggingCache(new Psr16Cache(new ArrayAdapter()), $logger);
        $key = self::cacheKey(Endpoints::WEATHER_WARNINGS);

        // Mirrors what `ApiConnector::fetchData()` does on a cache miss.
        self::assertNull($cache->get($key));
        $cache->set($key, ['warning'], 60);

        self::assertCount(1, $logger->records);
    }

    public function testDecoratorStillBehavesLikeTheCacheItWraps(): void
    {
        $cache = new LoggingCache(new Psr16Cache(new ArrayAdapter()), new RecordingLogger());
        $key = self::cacheKey(Endpoints::WEATHER_WARNINGS);

        $cache->set($key, ['warning'], 60);

        self::assertTrue($cache->has($key));
        self::assertSame(['warning'], $cache->get($key));
        self::assertTrue($cache->delete($key));
        self::assertSame('fallback', $cache->get($key, 'fallback'));
    }

    public function testKnownEndpointsAreLoggedByUrlRatherThanCacheKey(): void
    {
        $logger = new RecordingLogger();
        $cache = new LoggingCache(new Psr16Cache(new ArrayAdapter()), $logger);

        $cache->set(self::cacheKey(Endpoints::WEATHER_WARNINGS), ['warning'], 60);

        self::assertSame(Endpoints::WEATHER_WARNINGS, $logger->records[0]['context']['endpoint']);
    }

    public function testUnresolvableEndpointsAreLoggedByCacheKey(): void
    {
        $logger = new RecordingLogger();
        $cache = new LoggingCache(new Psr16Cache(new ArrayAdapter()), $logger);
        $endpoint = str_replace('{globalIdLocal}', '1010500', Endpoints::DAILY_WEATHER_FORECAST_BY_LOCATION);
        $key = self::cacheKey($endpoint);

        $cache->set($key, ['forecast'], 60);

        self::assertSame($key, $logger->records[0]['context']['endpoint']);
    }

    public function testTheUpstreamRoundTripIsTimedFromLookupToStore(): void
    {
        $logger = new RecordingLogger();
        $cache = new LoggingCache(new Psr16Cache(new ArrayAdapter()), $logger);
        $key = self::cacheKey(Endpoints::WEATHER_WARNINGS);

        $cache->get($key);
        usleep(5000);
        $cache->set($key, ['warning'], 60);

        self::assertGreaterThanOrEqual(5.0, $logger->records[0]['context']['duration_ms']);
    }

    public function testAStoreWithoutAPrecedingLookupIsLoggedWithoutADuration(): void
    {
        $logger = new RecordingLogger();
        $cache = new LoggingCache(new Psr16Cache(new ArrayAdapter()), $logger);

        $cache->set(self::cacheKey(Endpoints::WEATHER_WARNINGS), ['warning'], 60);

        self::assertCount(1, $logger->records);
        self::assertArrayNotHasKey('duration_ms', $logger->records[0]['context']);
    }

    public function testARefreshAfterACacheHitIsNotTimedAgainstTheHit(): void
    {
        $logger = new RecordingLogger();
        $cache = new LoggingCache(new Psr16Cache(new ArrayAdapter()), $logger);
        $key = self::cacheKey(Endpoints::WEATHER_WARNINGS);

        $cache->set($key, ['warning'], 60);
        $cache->get($key);
        $cache->set($key, ['fresher warning'], 60);

        self::assertArrayNotHasKey('duration_ms', $logger->records[1]['context']);
    }

    private static function cacheKey(string $endpoint): string
    {
        return 'ipma_api.' . hash('sha256', $endpoint);
    }
}
