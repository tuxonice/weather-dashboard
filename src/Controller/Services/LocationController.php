<?php

declare(strict_types=1);

namespace App\Controller\Services;

use App\Service\Astronomy\MoonPhase;
use App\Service\Astronomy\PlanetPosition;
use App\Service\Forecast\Warnings\AwarenessLevel;
use App\Service\Forecast\Warnings\WarningRepository;
use App\Service\ForecastRepository;
use App\Service\Services\LocationRepository;
use App\Service\WeatherDictionary;
use DateTime;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Tlab\SunCalc\SunCalc;
use Twig\Environment;

/**
 * Location browsing controller.
 *
 * - `index`: lists every district/island location grouped by region.
 * - `show`: renders the details for a single location (placeholder for the
 *   forecast view that will land in the next milestone).
 */
final class LocationController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LocationRepository $locations,
        private readonly ForecastRepository $forecasts,
        private readonly WarningRepository $warnings,
    ) {
    }

    public function index(): Response
    {
        try {
            $grouped = $this->locations->groupedByRegion();
            $error = null;
        } catch (IpmaApiException $e) {
            $grouped = [];
            $error = $e->getMessage();
        }

        $regionLabels = [];
        foreach (array_keys($grouped) as $regionId) {
            $regionLabels[$regionId] = LocationRepository::regionLabel($regionId);
        }

        $html = $this->twig->render('Services/location.index.html.twig', [
            'grouped' => $grouped,
            'region_labels' => $regionLabels,
            'error' => $error,
        ]);

        return new Response($html);
    }

    public function show(int $globalIdLocal, Request $request): Response
    {
        try {
            $location = $this->locations->findByGlobalId($globalIdLocal);
        } catch (IpmaApiException $e) {
            $html = $this->twig->render('Services/location.show.html.twig', [
                'location' => null,
                'timezone' => 'Europe/Lisbon',
                'error' => $e->getMessage(),
                'forecast_days' => [],
                'forecast_updated_at' => null,
                'forecast_error' => null,
                'area_warnings' => [],
                'sunrise' => null,
                'sunset' => null,
                'sunrise_end' => null,
                'sunset_start' => null,
                'solar_noon' => null,
                'nadir' => null,
                'dawn' => null,
                'dusk' => null,
                'nautical_dawn' => null,
                'nautical_dusk' => null,
                'night_end' => null,
                'night' => null,
                'golden_hour_end' => null,
                'golden_hour' => null,
                'day_length_minutes' => null,
                'moon_illumination' => null,
                'moon_distance_km' => null,
                'moon_azimuth_deg' => null,
                'moon_altitude_deg' => null,
                'sun_azimuth_deg' => null,
                'sun_altitude_deg' => null,
                'moonrise' => null,
                'moonset' => null,
                'upcoming_moon_phases' => [],
                'planets' => [],
                'visible_planets' => [],
            ]);

            return new Response($html, Response::HTTP_BAD_GATEWAY);
        }

        if ($location === null) {
            throw new NotFoundHttpException(sprintf('Location %d not found.', $globalIdLocal));
        }

        $forecastDays = [];
        $forecastUpdatedAt = null;
        $forecastError = null;

        try {
            $forecast = $this->forecasts->dailyForecast($globalIdLocal);
            $forecastUpdatedAt = $forecast->updatedAt;
            $forecastDays = array_map(
                static function ($record) {
                    $date = new DateTimeImmutable($record->forecastDate);

                    return [
                        'date' => $date,
                        'weather_type_id' => $record->idWeatherType,
                        'weather_type_label' => WeatherDictionary::weatherTypeLabel($record->idWeatherType),
                        'weather_type_icon' => WeatherDictionary::weatherTypeIcon($record->idWeatherType),
                        'min_temp' => $record->minTemp,
                        'max_temp' => $record->maxTemp,
                        'rainfall_prob' => $record->rainfallProb,
                        'rain_intensity_label' => WeatherDictionary::precipitationLabel($record->rainfallIntensity),
                        'wind_dir' => $record->winDir,
                        'wind_dir_label' => WeatherDictionary::windDirectionLabel($record->winDir),
                        'wind_speed_class' => $record->windSpeedClass,
                        'wind_speed_label' => WeatherDictionary::windSpeedLabel($record->windSpeedClass),
                    ];
                },
                $forecast->records,
            );
        } catch (IpmaApiException $e) {
            $forecastError = $e->getMessage();
        }

        $tz = new \DateTimeZone(LocationRepository::regionTimezone($location->idRegion));
        $areaWarnings = [];
        try {
            foreach ($this->warnings->forArea($location->idWarningArea) as $warning) {
                $areaWarnings[] = [
                    'warning' => $warning,
                    'level' => AwarenessLevel::meta($warning->awarenessLevelID),
                    'timezone' => $tz->getName(),
                ];
            }
        } catch (IpmaApiException) {
            // Warnings are non-critical — ignore upstream errors here so the
            // forecast view still renders. (The /warnings page surfaces errors.)
        }

        $nowLocal   = new DateTime('now', $tz);
        $sunCalc   = new SunCalc($nowLocal, $location->latitude, $location->longitude);
        $sunTimes  = $sunCalc->getSunTimes();

        $toLocal = static function (?DateTime $dt) use ($tz): ?DateTimeImmutable {
            return $dt !== null
                ? (new DateTimeImmutable('@' . $dt->getTimestamp()))->setTimezone($tz)
                : null;
        };

        $sunrise      = $toLocal($sunTimes['sunrise']      ?? null);
        $sunset       = $toLocal($sunTimes['sunset']       ?? null);
        $sunriseEnd   = $toLocal($sunTimes['sunriseEnd']   ?? null);
        $sunsetStart  = $toLocal($sunTimes['sunsetStart']  ?? null);
        $solarNoon    = $toLocal($sunTimes['solarNoon']    ?? null);
        $nadir        = $toLocal($sunTimes['nadir']        ?? null);
        $dawn         = $toLocal($sunTimes['dawn']         ?? null);
        $dusk         = $toLocal($sunTimes['dusk']         ?? null);
        $nauticalDawn = $toLocal($sunTimes['nauticalDawn'] ?? null);
        $nauticalDusk = $toLocal($sunTimes['nauticalDusk'] ?? null);
        $nightEnd     = $toLocal($sunTimes['nightEnd']     ?? null);
        $night        = $toLocal($sunTimes['night']        ?? null);
        $goldenHourEnd   = $toLocal($sunTimes['goldenHourEnd'] ?? null);
        $goldenHour      = $toLocal($sunTimes['goldenHour']    ?? null);

        $dayLengthMinutes = ($sunrise !== null && $sunset !== null)
            ? (int) round(($sunset->getTimestamp() - $sunrise->getTimestamp()) / 60)
            : null;

        $moonIllumination = $sunCalc->getMoonIllumination();
        $moonPosition     = $sunCalc->getMoonPosition($nowLocal);
        $moonDistanceKm   = (int) round($moonPosition->getDist());
        $moonAzimuthDeg   = round(fmod(rad2deg($moonPosition->getAzimuth()) + 180, 360), 1);
        $moonAltitudeDeg  = round(rad2deg($moonPosition->getAltitude()), 1);

        $sunCalcNow    = new SunCalc($nowLocal, $location->latitude, $location->longitude);
        $sunPosition   = $sunCalcNow->getSunPosition();
        $sunAzimuthDeg = round(fmod(rad2deg($sunPosition->getAzimuth()) + 180, 360), 1);
        $sunAltitudeDeg = round(rad2deg($sunPosition->getAltitude()), 1);

        $moonTimes = $sunCalc->getMoonTimes();
        $moonrise  = isset($moonTimes['moonrise']) ? (new DateTimeImmutable('@' . $moonTimes['moonrise']->getTimestamp()))->setTimezone($tz) : null;
        $moonset   = isset($moonTimes['moonset'])  ? (new DateTimeImmutable('@' . $moonTimes['moonset']->getTimestamp()))->setTimezone($tz)  : null;

        $nowUtc = new DateTimeImmutable('@' . $nowLocal->getTimestamp());
        $upcomingMoonPhases = array_map(
            static fn (array $phase): array => [
                'key'      => MoonPhase::translationKey($phase['type']),
                'emoji'    => MoonPhase::emoji($phase['type']),
                'datetime' => $phase['instant']->setTimezone($tz),
            ],
            MoonPhase::nextPrincipalPhases($nowUtc, 4),
        );

        // Calculate planet positions
        $planetPositions = PlanetPosition::getAllPlanetPositions($nowUtc, $location->latitude, $location->longitude);
        $visiblePlanets = array_filter($planetPositions, static fn(array $p): bool => $p['is_visible']);
        usort($visiblePlanets, static fn(array $a, array $b): int => $a['magnitude'] <=> $b['magnitude']);

        $html = $this->twig->render('Services/location.show.html.twig', [
            'location' => $location,
            'region_label' => LocationRepository::regionLabel($location->idRegion),
            'timezone' => $tz->getName(),
            'error' => null,
            'forecast_days' => $forecastDays,
            'forecast_updated_at' => $forecastUpdatedAt instanceof \DateTimeImmutable ? $forecastUpdatedAt->setTimezone($tz) : null,
            'forecast_error' => $forecastError,
            'area_warnings' => $areaWarnings,
            'sunrise' => $sunrise,
            'sunset' => $sunset,
            'sunrise_end' => $sunriseEnd,
            'sunset_start' => $sunsetStart,
            'solar_noon' => $solarNoon,
            'nadir' => $nadir,
            'dawn' => $dawn,
            'dusk' => $dusk,
            'nautical_dawn' => $nauticalDawn,
            'nautical_dusk' => $nauticalDusk,
            'night_end' => $nightEnd,
            'night' => $night,
            'golden_hour_end' => $goldenHourEnd,
            'golden_hour' => $goldenHour,
            'day_length_minutes' => $dayLengthMinutes,
            'moon_illumination' => $moonIllumination,
            'moon_distance_km' => $moonDistanceKm,
            'moon_azimuth_deg' => $moonAzimuthDeg,
            'moon_altitude_deg' => $moonAltitudeDeg,
            'sun_azimuth_deg' => $sunAzimuthDeg,
            'sun_altitude_deg' => $sunAltitudeDeg,
            'moonrise' => $moonrise,
            'moonset' => $moonset,
            'upcoming_moon_phases' => $upcomingMoonPhases,
            'planets' => $planetPositions,
            'visible_planets' => $visiblePlanets,
        ]);

        return new Response($html);
    }
}
