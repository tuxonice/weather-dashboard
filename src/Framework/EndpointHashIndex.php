<?php

declare(strict_types=1);

namespace App\Framework;

use BackedEnum;
use ReflectionClass;
use Tlab\IpmaApi\Endpoints;
use Tlab\IpmaApi\Enums\ForecastDayEnum;
use Tlab\IpmaApi\Enums\ForecastFireRiskDayEnum;
use Tlab\IpmaApi\Enums\SeaStateForecastDayEnum;
use Tlab\IpmaApi\Enums\SeismicInformationAreaEnum;

/**
 * Maps an IPMA cache key back to the endpoint URL it was derived from.
 *
 * `ApiConnector` keys its cache entries by `sha256()` of the request URL, so
 * the URL is not recoverable from the key itself. This rebuilds the mapping
 * in the other direction: every endpoint the library can request is hashed
 * up front and indexed by cache key.
 *
 * Endpoints whose placeholder is backed by an enum ({@see ForecastDayEnum}
 * and friends) are expanded; the per-location forecast and the climate CSVs
 * take open-ended identifiers and cannot be enumerated, so those resolve to
 * `null` and are logged by cache key instead.
 */
final class EndpointHashIndex
{
    /** Default `keyPrefix` of `Tlab\IpmaApi\ApiConnector`. */
    public const KEY_PREFIX = 'ipma_api.';

    /** @var array<string, list<class-string<BackedEnum>>> */
    private const ENUMERABLE_PLACEHOLDERS = [
        '{idDay}' => [ForecastDayEnum::class, ForecastFireRiskDayEnum::class, SeaStateForecastDayEnum::class],
        '{idArea}' => [SeismicInformationAreaEnum::class],
    ];

    /** @var array<string, string>|null */
    private static ?array $index = null;

    public static function resolve(string $cacheKey): ?string
    {
        if (self::$index === null) {
            self::$index = self::build();
        }

        return self::$index[$cacheKey] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function build(): array
    {
        $index = [];

        /** @var mixed $template */
        foreach ((new ReflectionClass(Endpoints::class))->getConstants() as $name => $template) {
            if ($name === 'BASE_URL' || !is_string($template)) {
                continue;
            }

            foreach (self::expand($template) as $url) {
                $index[self::KEY_PREFIX . hash('sha256', $url)] = $url;
            }
        }

        return $index;
    }

    /**
     * @return list<string>
     */
    private static function expand(string $template): array
    {
        $urls = [$template];

        foreach (self::ENUMERABLE_PLACEHOLDERS as $placeholder => $enums) {
            if (!str_contains($template, $placeholder)) {
                continue;
            }

            $values = [];
            foreach ($enums as $enum) {
                foreach ($enum::cases() as $case) {
                    $values[(string) $case->value] = true;
                }
            }

            $expanded = [];
            foreach ($urls as $url) {
                foreach (array_keys($values) as $value) {
                    $expanded[] = str_replace($placeholder, (string) $value, $url);
                }
            }
            $urls = $expanded;
        }

        return array_values(array_filter($urls, static fn(string $url): bool => !str_contains($url, '{')));
    }
}
