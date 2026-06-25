<?php

declare(strict_types=1);

namespace App\Framework;

use App\Controller\Forecast\Meteorology\FireRiskController;
use App\Controller\Forecast\Meteorology\UvController;
use App\Controller\Forecast\Oceanography\SeaController;
use App\Controller\Forecast\Warnings\WarningController;
use App\Controller\GlossaryController;
use App\Controller\HomeController;
use App\Controller\Observation\Biology\BivalveController;
use App\Controller\Observation\Climate\ClimateController;
use App\Controller\Observation\Meteorology\StationController;
use App\Controller\Observation\Seismic\SeismicController;
use App\Controller\OutlookController;
use App\Controller\TermsController;
use App\Controller\Services\LocationController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Central place to declare the application's routes.
 *
 * Kept as a plain PHP collection (rather than attributes or YAML) to
 * keep the dependency surface minimal during bootstrap.
 */
final class RouteLoader
{
    /**
     * Translated path segments per locale.
     *
     * Keys are logical segment names; values are per-locale slugs.
     * Used to build locale-prefixed, translated URLs like /pt/clima or /en/climate.
     */
    private const SEGMENTS = [
        'locations'           => ['pt' => 'localidades',        'en' => 'locations'],
        'warnings'            => ['pt' => 'avisos',              'en' => 'warnings'],
        'sea'                 => ['pt' => 'estado-do-mar',       'en' => 'sea-state'],
        'stations'            => ['pt' => 'estacoes-meteorologicas', 'en' => 'weather-stations'],
        'stations_map'        => ['pt' => 'mapa',                'en' => 'map'],
        'fire_risk'           => ['pt' => 'risco-incendio',      'en' => 'fire-risk'],
        'uv'                  => ['pt' => 'risco-de-uv',         'en' => 'uv-risk'],
        'outlook'             => ['pt' => 'previsao',            'en' => 'outlook'],
        'day_tomorrow'        => ['pt' => 'amanha',              'en' => 'tomorrow'],
        'day_day_after'       => ['pt' => 'depois-de-amanha',    'en' => 'day-after'],
        'seismic'             => ['pt' => 'informacao-sismica',  'en' => 'seismic-information'],
        'bivalves'            => ['pt' => 'bivalves',            'en' => 'bivalves'],
        'climate'             => ['pt' => 'clima',               'en' => 'climate'],
        'climate_max'         => ['pt' => 'temperatura-maxima',  'en' => 'max-temperature'],
        'climate_min'         => ['pt' => 'temperatura-minima',  'en' => 'min-temperature'],
        'climate_precip'      => ['pt' => 'precipitacao',        'en' => 'precipitation'],
        'climate_et0'         => ['pt' => 'evapotranspiracao',   'en' => 'evapotranspiration'],
        'climate_pdsi'        => ['pt' => 'pdsi',                'en' => 'pdsi'],
        'terms'               => ['pt' => 'termos',              'en' => 'terms'],
        'glossary'            => ['pt' => 'glossario',           'en' => 'glossary'],
    ];

    /** @var list<string> */
    public const SUPPORTED_LOCALES = ['pt', 'en'];

    public static function load(): RouteCollection
    {
        $routes = new RouteCollection();

        // Root redirect: / → /pt
        $routes->add('root', new Route(
            path: '/',
            defaults: ['_controller' => [HomeController::class, 'redirect']],
            methods: ['GET'],
        ));

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $s = self::SEGMENTS;

            // Home
            $routes->add("home.$locale", new Route(
                path: "/$locale",
                defaults: ['_controller' => [HomeController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));

            // Locations
            $routes->add("locations_index.$locale", new Route(
                path: "/$locale/{$s['locations'][$locale]}",
                defaults: ['_controller' => [LocationController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));
            $routes->add("locations_show.$locale", new Route(
                path: "/$locale/{$s['locations'][$locale]}/{globalIdLocal}",
                defaults: ['_controller' => [LocationController::class, 'show'], '_locale' => $locale],
                requirements: ['globalIdLocal' => '\d+'],
                methods: ['GET'],
            ));

            // Warnings
            $routes->add("warnings_index.$locale", new Route(
                path: "/$locale/{$s['warnings'][$locale]}/{day}",
                defaults: ['_controller' => [WarningController::class, 'index'], '_locale' => $locale, 'day' => 'today'],
                requirements: ['day' => $s['day_tomorrow'][$locale] . '|' . $s['day_day_after'][$locale]],
                methods: ['GET'],
            ));

            // Sea
            $routes->add("sea_index.$locale", new Route(
                path: "/$locale/{$s['sea'][$locale]}",
                defaults: ['_controller' => [SeaController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));
            $routes->add("sea_show.$locale", new Route(
                path: "/$locale/{$s['sea'][$locale]}/{globalIdLocal}",
                defaults: ['_controller' => [SeaController::class, 'show'], '_locale' => $locale],
                requirements: ['globalIdLocal' => '\d+'],
                methods: ['GET'],
            ));

            // Stations
            $routes->add("stations_index.$locale", new Route(
                path: "/$locale/{$s['stations'][$locale]}",
                defaults: ['_controller' => [StationController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));
            $routes->add("stations_map.$locale", new Route(
                path: "/$locale/{$s['stations'][$locale]}/{$s['stations_map'][$locale]}",
                defaults: ['_controller' => [StationController::class, 'map'], '_locale' => $locale],
                methods: ['GET'],
            ));
            $routes->add("stations_show.$locale", new Route(
                path: "/$locale/{$s['stations'][$locale]}/{id}",
                defaults: ['_controller' => [StationController::class, 'show'], '_locale' => $locale],
                requirements: ['id' => '\d+'],
                methods: ['GET'],
            ));

            // Fire risk
            $routes->add("fire_risk_index.$locale", new Route(
                path: "/$locale/{$s['fire_risk'][$locale]}/{day}",
                defaults: ['_controller' => [FireRiskController::class, 'index'], '_locale' => $locale, 'day' => 'today'],
                requirements: ['day' => $s['day_tomorrow'][$locale]],
                methods: ['GET'],
            ));

            // UV
            $routes->add("uv_index.$locale", new Route(
                path: "/$locale/{$s['uv'][$locale]}",
                defaults: ['_controller' => [UvController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));

            // Outlook
            $routes->add("outlook_index.$locale", new Route(
                path: "/$locale/{$s['outlook'][$locale]}/{day}",
                defaults: ['_controller' => [OutlookController::class, 'index'], '_locale' => $locale, 'day' => 'today'],
                requirements: ['day' => $s['day_tomorrow'][$locale] . '|' . $s['day_day_after'][$locale]],
                methods: ['GET'],
            ));

            // Seismic
            $routes->add("seismic_index.$locale", new Route(
                path: "/$locale/{$s['seismic'][$locale]}",
                defaults: ['_controller' => [SeismicController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));

            // Bivalves
            $routes->add("bivalves_index.$locale", new Route(
                path: "/$locale/{$s['bivalves'][$locale]}",
                defaults: ['_controller' => [BivalveController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));

            // Climate
            $routes->add("climate_index.$locale", new Route(
                path: "/$locale/{$s['climate'][$locale]}",
                defaults: ['_controller' => [ClimateController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));
            $routes->add("climate_max_temperature.$locale", new Route(
                path: "/$locale/{$s['climate'][$locale]}/{$s['climate_max'][$locale]}/{globalIdLocal}",
                defaults: ['_controller' => [ClimateController::class, 'maxTemperature'], '_locale' => $locale],
                requirements: ['globalIdLocal' => '\d+'],
                methods: ['GET'],
            ));
            $routes->add("climate_min_temperature.$locale", new Route(
                path: "/$locale/{$s['climate'][$locale]}/{$s['climate_min'][$locale]}/{globalIdLocal}",
                defaults: ['_controller' => [ClimateController::class, 'minTemperature'], '_locale' => $locale],
                requirements: ['globalIdLocal' => '\d+'],
                methods: ['GET'],
            ));
            $routes->add("climate_precipitation.$locale", new Route(
                path: "/$locale/{$s['climate'][$locale]}/{$s['climate_precip'][$locale]}/{globalIdLocal}",
                defaults: ['_controller' => [ClimateController::class, 'precipitation'], '_locale' => $locale],
                requirements: ['globalIdLocal' => '\d+'],
                methods: ['GET'],
            ));
            $routes->add("climate_evapotranspiration.$locale", new Route(
                path: "/$locale/{$s['climate'][$locale]}/{$s['climate_et0'][$locale]}/{globalIdLocal}",
                defaults: ['_controller' => [ClimateController::class, 'evapotranspiration'], '_locale' => $locale],
                requirements: ['globalIdLocal' => '\d+'],
                methods: ['GET'],
            ));
            $routes->add("climate_pdsi.$locale", new Route(
                path: "/$locale/{$s['climate'][$locale]}/{$s['climate_pdsi'][$locale]}/{globalIdLocal}",
                defaults: ['_controller' => [ClimateController::class, 'pdsi'], '_locale' => $locale],
                requirements: ['globalIdLocal' => '\d+'],
                methods: ['GET'],
            ));

            // Terms
            $routes->add("terms.$locale", new Route(
                path: "/$locale/{$s['terms'][$locale]}",
                defaults: ['_controller' => [TermsController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));

            // Glossary
            $routes->add("glossary.$locale", new Route(
                path: "/$locale/{$s['glossary'][$locale]}",
                defaults: ['_controller' => [GlossaryController::class, 'index'], '_locale' => $locale],
                methods: ['GET'],
            ));
        }

        return $routes;
    }

    public static function segment(string $name, string $locale): string
    {
        return self::SEGMENTS[$name][$locale];
    }

    /**
     * Returns the logical route name (without locale suffix) for a given full route name.
     * e.g. "climate_index.pt" → "climate_index"
     */
    public static function logicalName(string $routeName): string
    {
        foreach (self::SUPPORTED_LOCALES as $locale) {
            if (str_ends_with($routeName, ".$locale")) {
                return substr($routeName, 0, -strlen(".$locale"));
            }
        }

        return $routeName;
    }

    /**
     * Returns the route name for a given logical name and locale.
     * e.g. ("climate_index", "en") → "climate_index.en"
     */
    public static function localizedName(string $logicalName, string $locale): string
    {
        return "$logicalName.$locale";
    }
}
