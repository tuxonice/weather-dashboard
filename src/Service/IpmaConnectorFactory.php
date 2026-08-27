<?php

declare(strict_types=1);

namespace App\Service;

use App\Framework\FileLogger;
use App\Framework\LoggingCache;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Tlab\IpmaApi\ApiConnector;
use Tlab\IpmaApi\ApiConnectorInterface;

/**
 * Builds a PSR-16 cached IPMA API connector.
 *
 * The IPMA reference data (locations, stations, weather types, wind classes)
 * changes rarely; responses are cached on the local filesystem to keep the
 * dashboard responsive and avoid hammering the upstream API.
 *
 * As of `tuxonice/ipma-api` `dev-ipm-00-require-cache`, the library's
 * `Ipma*::create*Api()` factories accept a {@see CacheInterface} directly
 * (no longer an {@see ApiConnectorInterface}). {@see createCache()} exposes
 * that PSR-16 cache to the DI container; {@see create()} still builds the
 * connector for repositories that bypass those factories and hit the
 * endpoints via `fetchData()` / `fetchCsv()`.
 *
 * The cache is wrapped in a {@see LoggingCache} so that every request that
 * actually reaches IPMA (i.e. every cache miss the library then fills) is
 * recorded in `var/log/ipma-requests.log`.
 */
final class IpmaConnectorFactory
{
    public static function createCache(
        string $cacheDir,
        int $ttlSeconds = 3600,
        ?string $logFile = null,
        ?string $requestLogFile = null,
    ): CacheInterface {
        $adapter = new FilesystemAdapter(
            namespace: 'ipma',
            defaultLifetime: $ttlSeconds,
            directory: $cacheDir,
        );
        $adapter->setLogger(new FileLogger($logFile ?? $cacheDir . '/../log/ipma-cache.log'));

        return new LoggingCache(
            new Psr16Cache($adapter),
            new FileLogger($requestLogFile ?? $cacheDir . '/../log/ipma-requests.log', 'ipma-api'),
        );
    }

    public static function create(
        string $cacheDir,
        int $ttlSeconds = 3600,
        ?string $logFile = null,
        ?string $requestLogFile = null,
    ): ApiConnectorInterface {
        return new ApiConnector(
            self::createCache($cacheDir, $ttlSeconds, $logFile, $requestLogFile),
            ttlSeconds: $ttlSeconds,
        );
    }
}
