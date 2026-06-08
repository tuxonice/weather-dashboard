<?php

declare(strict_types=1);

namespace App\Controller;

use App\Framework\LocaleSubscriber;
use App\Service\Observation\Meteorology\StationExtremesService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Landing page controller.
 *
 * Acts as the entry-point smoke test for the scaffolded framework:
 * renders a Bootstrap-powered welcome page through Twig.
 */
final class HomeController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly StationExtremesService $extremes,
    ) {
    }

    public function index(): Response
    {
        $extremes = null;
        $extremes24h = null;
        try {
            $extremes    = $this->extremes->get();
            $extremes24h = $this->extremes->get24h();
        } catch (\Throwable) {
            // Non-critical — home page renders without the panels on API failure.
        }

        $html = $this->twig->render('Home/index.html.twig', [
            'title'       => 'IPMA Dashboard',
            'extremes'    => $extremes,
            'extremes24h' => $extremes24h,
        ]);

        return new Response($html);
    }

    public function redirect(): RedirectResponse
    {
        return new RedirectResponse('/' . LocaleSubscriber::DEFAULT, 302);
    }
}
