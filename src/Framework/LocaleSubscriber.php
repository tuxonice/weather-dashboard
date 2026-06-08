<?php

declare(strict_types=1);

namespace App\Framework;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

/**
 * Locale resolution from the `_locale` route attribute.
 *
 * On every request the locale is read from the `_locale` default baked
 * into the matched route (e.g. "pt" for `home.pt`, "en" for `home.en`).
 * It is forwarded to:
 *  - the Translator (so `|trans` resolves the right catalogue),
 *  - the request,
 *  - the Twig environment as globals for templates (app_locale,
 *    app_current_route, app_route_params).
 *  - the routing RequestContext so that path() calls automatically
 *    emit the correct locale-prefixed URL without needing an explicit
 *    _locale parameter in every call.
 *
 * The prefix-less root request (/) has no _locale, so it falls back to
 * the default locale before the root-redirect controller sends it on.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    public const SUPPORTED = ['pt', 'en'];
    public const DEFAULT = 'pt';

    public function __construct(
        private readonly LocaleAwareInterface $translator,
        private readonly Environment $twig,
        private readonly RequestContext $requestContext,
        private readonly LocalizedRoutingExtension $localizedRouting,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After RouterListener (priority 32) so _locale is already set.
            KernelEvents::REQUEST => ['onKernelRequest', 16],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Prefer the locale baked into the matched route; fall back to default
        // for the prefix-less root request.
        $routeLocale = $request->attributes->get('_locale');
        $locale = is_string($routeLocale) && in_array($routeLocale, self::SUPPORTED, true)
            ? $routeLocale
            : self::DEFAULT;

        $request->setLocale($locale);
        $this->translator->setLocale($locale);
        $this->localizedRouting->setLocale($locale);

        // Keep RequestContext in sync so path() fills {_locale} automatically.
        $this->requestContext->setParameter('_locale', $locale);

        // Expose to templates.
        $this->twig->addGlobal('app_locale', $locale);
        $this->twig->addGlobal('app_supported_locales', self::SUPPORTED);
        $this->twig->addGlobal('app_request', $request);

        // Current route name (e.g. "climate_index.pt") and its params —
        // used by the language switcher to build the alternate-locale URL.
        $routeName   = $request->attributes->get('_route', '');
        $routeParams = $request->attributes->get('_route_params', []);
        $this->twig->addGlobal('app_current_route', $routeName);
        $this->twig->addGlobal('app_route_params', $routeParams);
    }
}
