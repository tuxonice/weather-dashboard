<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\IpmaConnectorFactory;
use PHPUnit\Framework\TestCase;
use Tlab\IpmaApi\Endpoints;

final class IpmaConnectorFactoryTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/ipma-connector-factory-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->workDir);
    }

    public function testTheCacheItBuildsLogsUpstreamFetchesToTheRequestLog(): void
    {
        $requestLog = $this->workDir . '/log/ipma-requests.log';
        $cache = IpmaConnectorFactory::createCache(
            $this->workDir . '/cache',
            60,
            $this->workDir . '/log/ipma-cache.log',
            $requestLog,
        );

        $key = 'ipma_api.' . hash('sha256', Endpoints::WEATHER_WARNINGS);
        self::assertNull($cache->get($key));
        $cache->set($key, ['warning'], 60);

        self::assertFileExists($requestLog);
        self::assertStringContainsString(Endpoints::WEATHER_WARNINGS, (string) file_get_contents($requestLog));
    }

    public function testReadingACachedResponseIsNotLoggedAsAnUpstreamFetch(): void
    {
        $requestLog = $this->workDir . '/log/ipma-requests.log';
        $cache = IpmaConnectorFactory::createCache(
            $this->workDir . '/cache',
            60,
            $this->workDir . '/log/ipma-cache.log',
            $requestLog,
        );

        $key = 'ipma_api.' . hash('sha256', Endpoints::WEATHER_WARNINGS);
        $cache->set($key, ['warning'], 60);
        $cache->get($key);
        $cache->get($key);

        self::assertSame(1, substr_count((string) file_get_contents($requestLog), 'FETCH'));
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || !is_string($entry)) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? self::removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
