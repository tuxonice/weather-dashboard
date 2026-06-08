<?php

declare(strict_types=1);

namespace App\Controller\Observation\Meteorology;

use App\Service\Observation\Meteorology\StationHourlyRepository;
use App\Service\Observation\Meteorology\StationObservationRepository;
use App\Service\Observation\Meteorology\StationRepository;
use App\Service\Observation\Meteorology\StationWindDirection;
use App\Service\Observation\Meteorology\WindRose;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Weather-station browsing controller.
 *
 * - `index`: lists every station together with its latest observation
 *   (temperature, humidity, wind).
 * - `show`:  renders 24h of history for a single station.
 * - `map`:   plots every station's latest-hour reading on a Leaflet map
 *            (powered by IPMA's `obs-surface.geojson` single-hour feed).
 */
final class StationController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly StationRepository $stations,
        private readonly StationObservationRepository $observations,
        private readonly StationHourlyRepository $hourly,
    ) {
    }

    public function index(): Response
    {
        $error = null;
        $rows = [];
        $updatedAt = null;

        try {
            $stations = $this->stations->sortedByName();
            $latest = $this->observations->latestAll();
            $updatedAt = $latest['at'];
            $byStation = $latest['observations'];

            foreach ($stations as $station) {
                $obs = $byStation[$station->id] ?? null;

                $rows[] = [
                    'station' => $station,
                    'has_data' => $obs !== null,
                    'temperature_c' => $obs?->temperatureC,
                    'humidity_pct' => $obs?->humidityPct,
                    'wind_speed_kmh' => $obs?->windSpeedKmh,
                    'wind_dir_code' => StationWindDirection::code($obs?->windDirectionId),
                    'wind_dir_label' => StationWindDirection::label($obs?->windDirectionId),
                ];
            }
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        }

        $tz = new \DateTimeZone('Europe/Lisbon');

        $html = $this->twig->render('Observation/Meteorology/station.index.html.twig', [
            'rows' => $rows,
            'updated_at' => $updatedAt?->setTimezone($tz),
            'timezone' => $tz->getName(),
            'error' => $error,
        ]);

        return new Response($html);
    }

    public function show(int $id): Response
    {
        try {
            $station = $this->stations->findById($id);
        } catch (IpmaApiException $e) {
            return new Response(
                $this->twig->render('Observation/Meteorology/station.show.html.twig', [
                    'station' => null,
                    'history' => [],
                    'latest' => null,
                    'timezone' => 'Europe/Lisbon',
                    'error' => $e->getMessage(),
                ]),
                Response::HTTP_BAD_GATEWAY,
            );
        }

        if ($station === null) {
            throw new NotFoundHttpException(sprintf('Station %d not found.', $id));
        }

        $history = [];
        $latest = null;
        $observationError = null;
        $directionIds = [];
        $windSpeeds = [];

        try {
            foreach ($this->observations->historyForStation($id) as $entry) {
                $obs = $entry['observation'];
                $directionIds[] = $obs->windDirectionId;
                $windSpeeds[] = $obs->windSpeedKmh;
                $row = [
                    'at' => $entry['at'],
                    'temperature_c' => $obs->temperatureC,
                    'humidity_pct' => $obs->humidityPct,
                    'pressure_hpa' => $obs->pressureHpa,
                    'precipitation_mm' => $obs->precipitationMm,
                    'radiation_wm2' => $obs->radiationWm2,
                    'wind_speed_kmh' => $obs->windSpeedKmh,
                    'wind_speed_ms' => $obs->windSpeedMs,
                    'wind_dir_code' => StationWindDirection::code($obs->windDirectionId),
                    'wind_dir_label' => StationWindDirection::label($obs->windDirectionId),
                    'wind_dir_bearing' => StationWindDirection::bearing($obs->windDirectionId),
                ];
                $history[] = $row;
                $latest = $row;
            }
        } catch (IpmaApiException $e) {
            $observationError = $e->getMessage();
        }

        // History is oldest→newest from the repository; reverse for the
        // table so the newest reading is at the top.
        $history = array_reverse($history);

        $tz = new \DateTimeZone('Europe/Lisbon');
        $convertAt = static fn(array $row): array => array_merge($row, ['at' => $row['at']->setTimezone($tz)]);
        $history = array_map($convertAt, $history);
        $latest = $latest !== null ? $convertAt($latest) : null;

        // Aggregate the 24h direction readings into an 8-sector wind rose,
        // averaging wind speed per sector for the colour scale.
        // Suppress the chart entirely when no directional wind was recorded.
        $windRose = WindRose::tally($directionIds, $windSpeeds);
        $hasDirectionalWind = array_sum(array_column($windRose['sectors'], 'count')) > 0;

        // Chronological (oldest→newest) per-metric series for the 24h trend
        // charts. Each metric is dropped when it has no readings at all; the
        // values array keeps nulls so gaps line up with the shared time axis.
        $chronological = array_reverse($history);
        $trendLabels = array_column($chronological, 'at');

        $metricDefs = [
            ['id' => 'temperature', 'field' => 'temperature_c',  'heading' => 'station.temp_trend_heading',       'icon' => 'bi-thermometer-half', 'unit' => '°C',   'color' => '220, 53, 69',   'decimals' => 1, 'zero' => false],
            ['id' => 'humidity',    'field' => 'humidity_pct',   'heading' => 'station.humidity_trend_heading',   'icon' => 'bi-droplet-half',     'unit' => '%',    'color' => '13, 202, 240',  'decimals' => 0, 'zero' => false],
            ['id' => 'pressure',    'field' => 'pressure_hpa',   'heading' => 'station.pressure_trend_heading',   'icon' => 'bi-speedometer',      'unit' => 'hPa',  'color' => '108, 117, 125', 'decimals' => 1, 'zero' => false],
            ['id' => 'wind_speed',  'field' => 'wind_speed_kmh', 'heading' => 'station.wind_speed_heading',       'icon' => 'bi-wind',             'unit' => 'km/h', 'color' => '13, 110, 253',  'decimals' => 1, 'zero' => true],
        ];

        $trendCharts = [];
        foreach ($metricDefs as $def) {
            $values = array_column($chronological, $def['field']);
            if (array_filter($values, static fn($v): bool => $v !== null) === []) {
                continue;
            }
            $chart = [
                'id'       => $def['id'],
                'heading'  => $def['heading'],
                'icon'     => $def['icon'],
                'unit'     => $def['unit'],
                'color'    => $def['color'],
                'decimals' => $def['decimals'],
                'zero'     => $def['zero'],
                'values'   => $values,
            ];

            // The wind-speed chart draws each point as an arrow rotated to the
            // wind direction (bearing it blows *from*); calm/unknown hours have
            // a null bearing and fall back to a plain dot.
            if ($def['id'] === 'wind_speed') {
                $chart['bearings'] = array_column($chronological, 'wind_dir_bearing');
                $chart['dir_codes'] = array_column($chronological, 'wind_dir_code');
            }

            $trendCharts[] = $chart;
        }

        $html = $this->twig->render('Observation/Meteorology/station.show.html.twig', [
            'station' => $station,
            'history' => $history,
            'latest' => $latest,
            'timezone' => $tz->getName(),
            'error' => null,
            'observation_error' => $observationError,
            'wind_rose' => $hasDirectionalWind ? $windRose : null,
            'wind_rose_calm' => $windRose['calm'],
            'trend_labels' => $trendLabels,
            'trend_charts' => $trendCharts,
        ]);

        return new Response($html);
    }

    public function map(): Response
    {
        $features = [];
        $error = null;
        $updatedAt = null;
        $stats = ['temp_min' => null, 'temp_max' => null, 'count' => 0];

        try {
            foreach ($this->hourly->all() as $o) {
                // The library returns 0.0 for missing coordinates when the
                // geometry is absent; skip those rather than dropping a
                // marker in the Atlantic.
                if ($o->latitude === 0.0 && $o->longitude === 0.0) {
                    continue;
                }

                // `idEstacao` is the only field we really need to key on.
                if ($o->idEstacao === null) {
                    continue;
                }

                if ($o->time !== null && $updatedAt === null) {
                    try {
                        $updatedAt = new \DateTimeImmutable($o->time, new \DateTimeZone('UTC'));
                    } catch (\Exception) {
                    }
                }

                $temp = $o->temperatura;
                if ($temp !== null) {
                    $stats['temp_min'] = $stats['temp_min'] === null ? $temp : min($stats['temp_min'], $temp);
                    $stats['temp_max'] = $stats['temp_max'] === null ? $temp : max($stats['temp_max'], $temp);
                }
                $stats['count']++;

                $features[] = [
                    'id'             => $o->idEstacao,
                    'name'           => $o->localEstacao,
                    'lat'            => $o->latitude,
                    'lng'            => $o->longitude,
                    'temperature_c'  => $temp,
                    'humidity_pct'   => $o->humidade,
                    'pressure_hpa'   => $o->pressao,
                    'precipitation_mm' => $o->precAcumulada,
                    'radiation_wm2'  => $o->radiacao,
                    'wind_speed_kmh' => $o->intensidadeVentoKM,
                    'wind_dir_id'    => $o->idDireccVento,
                    'wind_dir_code'  => StationWindDirection::code($o->idDireccVento),
                    'wind_dir_label' => StationWindDirection::label($o->idDireccVento),
                ];
            }
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        }

        $tz = new \DateTimeZone('Europe/Lisbon');

        $html = $this->twig->render('Observation/Meteorology/station.map.html.twig', [
            'stations_json' => json_encode($features, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'stations_count' => $stats['count'],
            'temp_min' => $stats['temp_min'],
            'temp_max' => $stats['temp_max'],
            'updated_at' => $updatedAt?->setTimezone($tz),
            'timezone' => $tz->getName(),
            'error' => $error,
        ]);

        return new Response($html);
    }
}
