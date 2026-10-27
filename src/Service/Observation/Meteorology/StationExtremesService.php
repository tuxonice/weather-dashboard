<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

use DateTimeImmutable;
use DateTimeZone;
use Tlab\IpmaApi\Dto\Service\WeatherStation;

/**
 * Derives the hottest and coldest station readings from the latest
 * observation snapshot, joining them with station catalogue data.
 */
final class StationExtremesService
{
    public function __construct(
        private readonly StationRepository $stations,
        private readonly StationObservationRepository $observations,
    ) {
    }

    /**
     * Returns the hottest and coldest stations from the latest observation
     * snapshot, or null for either if no temperature data is available.
     *
     * @return array{
     *   hottest:    array{station: WeatherStation|null, temperature_c: float|null, at: DateTimeImmutable|null}|null,
     *   coldest:    array{station: WeatherStation|null, temperature_c: float|null, at: DateTimeImmutable|null}|null,
     *   updated_at: DateTimeImmutable|null,
     * }
     */
    public function get(): array
    {
        $latest = $this->observations->latestAll();
        $observations = $latest['observations'];
        $updatedAt = $latest['at'];

        $hottestId = null;
        $hottestTemp = null;
        $coldestId = null;
        $coldestTemp = null;

        foreach ($observations as $stationId => $obs) {
            $temp = $obs->temperatureC;
            if ($temp === null) {
                continue;
            }

            if ($hottestTemp === null || $temp > $hottestTemp) {
                $hottestTemp = $temp;
                $hottestId = $stationId;
            }

            if ($coldestTemp === null || $temp < $coldestTemp) {
                $coldestTemp = $temp;
                $coldestId = $stationId;
            }
        }

        $tz = new DateTimeZone('Europe/Lisbon');

        $at = $updatedAt?->setTimezone($tz);

        return [
            'hottest' => $hottestId !== null
                ? ['station' => $this->stations->findById($hottestId), 'temperature_c' => $hottestTemp, 'at' => $at]
                : null,
            'coldest' => $coldestId !== null
                ? ['station' => $this->stations->findById($coldestId), 'temperature_c' => $coldestTemp, 'at' => $at]
                : null,
            'updated_at' => $at,
        ];
    }

    /**
     * Returns the hottest and coldest readings across all stations over the
     * last 24 hours, with the time at which each extreme occurred.
     *
     * @return array{
     *   hottest: array{station: WeatherStation|null, temperature_c: float, at: DateTimeImmutable}|null,
     *   coldest: array{station: WeatherStation|null, temperature_c: float, at: DateTimeImmutable}|null,
     * }
     */
    public function get24h(): array
    {
        $raw = $this->observations->extremesAllStations24h();
        $tz  = new DateTimeZone('Europe/Lisbon');

        $hottest = null;
        if ($raw['hottest'] !== null) {
            $hottest = [
                'station'       => $this->stations->findById($raw['hottest']['station_id']),
                'temperature_c' => $raw['hottest']['temperature_c'],
                'at'            => $raw['hottest']['at']->setTimezone($tz),
            ];
        }

        $coldest = null;
        if ($raw['coldest'] !== null) {
            $coldest = [
                'station'       => $this->stations->findById($raw['coldest']['station_id']),
                'temperature_c' => $raw['coldest']['temperature_c'],
                'at'            => $raw['coldest']['at']->setTimezone($tz),
            ];
        }

        return ['hottest' => $hottest, 'coldest' => $coldest];
    }

    /**
     * Station with the most rain over the last 24 hours and the heaviest
     * single hourly reading (with its time).
     *
     * @return array{
     *   wettest: array{station: WeatherStation|null, precipitation_mm: float}|null,
     *   peak:    array{station: WeatherStation|null, precipitation_mm: float, at: DateTimeImmutable}|null,
     * }
     */
    public function getRain24h(): array
    {
        $raw = $this->observations->rainExtremes24h();
        $tz  = new DateTimeZone('Europe/Lisbon');

        $wettest = null;
        if ($raw['wettest'] !== null) {
            $wettest = [
                'station'          => $this->stations->findById($raw['wettest']['station_id']),
                'precipitation_mm' => $raw['wettest']['precipitation_mm'],
            ];
        }

        $peak = null;
        if ($raw['peak'] !== null) {
            $peak = [
                'station'          => $this->stations->findById($raw['peak']['station_id']),
                'precipitation_mm' => $raw['peak']['precipitation_mm'],
                'at'               => $raw['peak']['at']->setTimezone($tz),
            ];
        }

        return ['wettest' => $wettest, 'peak' => $peak];
    }
}
