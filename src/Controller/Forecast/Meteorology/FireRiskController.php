<?php

declare(strict_types=1);

namespace App\Controller\Forecast\Meteorology;

use App\Framework\RouteLoader;
use App\Service\Forecast\Meteorology\FireRiskLevel;
use App\Service\Forecast\Meteorology\FireRiskRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tlab\IpmaApi\Enums\ForecastFireRiskDayEnum;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Fire-risk (Risco de incêndio, RCM) forecast view.
 *
 * Renders the per-municipality (DICO) risk level for today or tomorrow on
 * a Leaflet map with summary tiles (count per level).
 */
final class FireRiskController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly FireRiskRepository $fireRisk,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $urlDay = strtolower((string) $request->attributes->get('day', 'today'));
        $dayKey = $urlDay === RouteLoader::segment('day_tomorrow', $locale) ? 'tomorrow' : 'today';
        $day = $dayKey === 'tomorrow'
            ? ForecastFireRiskDayEnum::TOMORROW
            : ForecastFireRiskDayEnum::TODAY;

        $features = [];
        $counts = array_fill_keys(FireRiskLevel::all(), 0);
        $forecastDate = null;
        $runDate = null;
        $error = null;

        try {
            $result = $this->fireRisk->forDay($day);
            $forecastDate = $result['forecast_date'];
            $runDate = $result['run_date'];

            foreach ($result['records'] as $record) {
                $level = $record->fireRiskLevel;
                if (isset($counts[$level])) {
                    $counts[$level]++;
                }

                $features[] = [
                    'dico' => $record->dico,
                    'lat' => $record->latitude,
                    'lng' => $record->longitude,
                    'level' => $level,
                    'level_label' => $this->translator->trans(FireRiskLevel::label($level)),
                    'color' => FireRiskLevel::color($level),
                ];
            }
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        }

        $legend = [];
        foreach (FireRiskLevel::all() as $level) {
            $legend[] = [
                'level' => $level,
                'label' => FireRiskLevel::label($level),
                'color' => FireRiskLevel::color($level),
                'bootstrap' => FireRiskLevel::bootstrap($level),
                'count' => $counts[$level],
            ];
        }

        $html = $this->twig->render('Forecast/Meteorology/fire-risk.index.html.twig', [
            'day' => $dayKey,
            'forecast_date' => $forecastDate,
            'run_date' => $runDate,
            'features_json' => json_encode($features, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'features_count' => count($features),
            'legend' => $legend,
            'error' => $error,
            // Override matched URL slug with the internal day key so cross-locale
            // links (hreflang, language switcher) can translate it via LocalizedRoutingExtension.
            'app_route_params' => ['day' => $dayKey],
        ]);

        return new Response($html);
    }
}
