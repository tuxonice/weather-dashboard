<?php

declare(strict_types=1);

namespace App\Framework;

use DateInterval;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 decorator that logs every *real* HTTP request to the IPMA API.
 *
 * The upstream `Tlab\IpmaApi\ApiConnector` builds its own HTTP client and is
 * instantiated inside the library's `Ipma*::create*Api()` factories, so the
 * app has no seam at the transport layer. The PSR-16 cache it is handed is
 * that seam: `ApiConnector` reads the cache first and only calls
 * `$this->cache->set()` *after* a successful upstream fetch — so one `set()`
 * on an `ipma_api.*` key is exactly one request that left the container.
 *
 * The time between the lookup that missed and the store that followed is the
 * upstream round trip (transport plus JSON/CSV decoding) and is logged with
 * it. Cache hits are not logged; entries written without a preceding lookup
 * (a warm-up script, say) are logged without a duration.
 */
final class LoggingCache implements CacheInterface
{
    /**
     * Lookups that missed and are presumed to be in flight upstream,
     * as `cache key => microtime(true)` of the miss.
     *
     * @var array<string, float>
     */
    private array $pendingLookups = [];

    public function __construct(
        private readonly CacheInterface $inner,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->inner->get($key, $default);

        if ($value === $default && self::isIpmaResponse($key)) {
            $this->pendingLookups[$key] = microtime(true);
        }

        return $value;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $stored = $this->inner->set($key, $value, $ttl);

        if (self::isIpmaResponse($key)) {
            $this->logFetch($key);
        }

        return $stored;
    }

    public function delete(string $key): bool
    {
        return $this->inner->delete($key);
    }

    public function clear(): bool
    {
        $this->pendingLookups = [];

        return $this->inner->clear();
    }

    /**
     * @param iterable<string> $keys
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->inner->getMultiple($keys, $default);
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        return $this->inner->setMultiple($values, $ttl);
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->inner->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    private function logFetch(string $key): void
    {
        $startedAt = $this->pendingLookups[$key] ?? null;
        unset($this->pendingLookups[$key]);

        $context = ['endpoint' => EndpointHashIndex::resolve($key) ?? $key];

        if ($startedAt === null) {
            $this->logger->info('FETCH {endpoint}', $context);

            return;
        }

        $context['duration_ms'] = round((microtime(true) - $startedAt) * 1000, 1);
        $this->logger->info('FETCH {endpoint} ({duration_ms} ms)', $context);
    }

    private static function isIpmaResponse(string $key): bool
    {
        return str_starts_with($key, EndpointHashIndex::KEY_PREFIX);
    }
}
