<?php

declare(strict_types=1);

namespace App\Controller\Forecast\Warnings;

use App\Framework\RouteLoader;
use App\Service\Forecast\Warnings\AwarenessLevel;
use App\Service\Forecast\Warnings\WarningRepository;
use App\Service\Services\LocationRepository;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Lists active meteorological warnings published by IPMA.
 */
final class WarningController
{
    private const MISSING_WARNING_LOCATION_NAME = [
        'MCN' => 'Madeira-Costa Norte',
        'MRM' => 'Madeira-R. Montanhosas',
    ];

    private const DAY_KEYS = ['today', 'tomorrow', 'day-after'];

    private const DAY_META = [
        'today'     => ['label' => 'outlook.day.today',     'segment' => null],
        'tomorrow'  => ['label' => 'outlook.day.tomorrow',  'segment' => 'day_tomorrow'],
        'day-after' => ['label' => 'outlook.day.day_after', 'segment' => 'day_day_after'],
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly WarningRepository $warnings,
        private readonly LocationRepository $locations,
    ) {
    }

    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $urlDay = strtolower((string) $request->attributes->get('day', 'today'));

        $dayKey = 'today';
        foreach (self::DAY_META as $key => $meta) {
            if ($meta['segment'] !== null && RouteLoader::segment($meta['segment'], $locale) === $urlDay) {
                $dayKey = $key;
                break;
            }
        }

        $dayTabs = [];
        foreach (self::DAY_META as $key => $meta) {
            $dayTabs[] = [
                'key'    => $key,
                'label'  => $meta['label'],
                'active' => $key === $dayKey,
            ];
        }

        $groupedByArea = [];
        $error = null;

        try {
            $groupedByArea = $this->warnings->activeGroupedByArea();
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        }

        // Compute the UTC midnight boundaries for today, tomorrow, and day-after.
        $utc = new DateTimeZone('UTC');
        $today = new DateTimeImmutable('today', $utc);
        /** @var array<string, DateTimeImmutable> $dayStarts */
        $dayStarts = [
            'today'     => $today,
            'tomorrow'  => $today->modify('+1 day'),
            'day-after' => $today->modify('+2 days'),
        ];

        // Decorate each warning with its level metadata and timezone.
        // Bucket into every day whose UTC window it overlaps.
        /** @var array<string, array<string, list<array<string,mixed>>>> $groupedByDay */
        $groupedByDay = ['today' => [], 'tomorrow' => [], 'day-after' => []];

        foreach ($groupedByArea as $area => $warnings) {
            $location     = $this->locations->findByIdWarningArea($area);
            $locationName = $location->name ?? self::MISSING_WARNING_LOCATION_NAME[$area] ?? $area;
            $timezone     = LocationRepository::regionTimezone($location->idRegion ?? 1);

            foreach ($warnings as $w) {
                $item = [
                    'warning'  => $w,
                    'level'    => AwarenessLevel::meta($w->awarenessLevelID),
                    'timezone' => $timezone,
                ];

                $wStart = new DateTimeImmutable($w->startTime, $utc);
                $wEnd   = new DateTimeImmutable($w->endTime, $utc);

                foreach (self::DAY_KEYS as $dk) {
                    $dayStart = $dayStarts[$dk];
                    $dayEnd   = $dayStart->modify('+1 day');
                    if ($wStart < $dayEnd && $wEnd > $dayStart) {
                        $groupedByDay[$dk][$locationName][] = $item;
                    }
                }
            }
        }

        // Build map levels for the active day only (SVG colouring).
        $mapLevels = [];
        foreach ($this->warnings->active() as $w) {
            $area   = $w->warningIdArea;
            $wStart = new DateTimeImmutable($w->startTime, $utc);
            $wEnd   = new DateTimeImmutable($w->endTime, $utc);
            $dayStart = $dayStarts[$dayKey];
            $dayEnd   = $dayStart->modify('+1 day');
            if ($wStart < $dayEnd && $wEnd > $dayStart) {
                $current = $mapLevels[$area] ?? AwarenessLevel::GREEN;
                if (AwarenessLevel::severity($w->awarenessLevelID) > AwarenessLevel::severity($current)) {
                    $mapLevels[$area] = $w->awarenessLevelID;
                }
            }
        }

        $html = $this->twig->render('Forecast/Warnings/warning.index.html.twig', [
            'day'              => $dayKey,
            'day_tabs'         => $dayTabs,
            'grouped'          => $groupedByDay[$dayKey],
            'map_levels_json'  => json_encode($mapLevels, JSON_THROW_ON_ERROR),
            'error'            => $error,
            'app_route_params' => ['day' => $dayKey],
        ]);

        return new Response($html);
    }
}
