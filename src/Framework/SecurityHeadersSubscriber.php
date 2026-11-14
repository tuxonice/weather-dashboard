<?php

declare(strict_types=1);

namespace App\Framework;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the Content-Security-Policy on every response.
 *
 * Strict policy: no inline scripts or styles, no inline style attributes.
 * Templates load their code from /js/pages and /css/pages, and dynamic
 * styles go through the `data-style` helper (public/js/pages/inline-styles.js).
 * The only remote origin allowed is OpenStreetMap map tiles.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public const CONTENT_SECURITY_POLICY = "default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self'; "
        . "img-src 'self' data: https://*.tile.openstreetmap.org; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "worker-src 'self'; "
        . "manifest-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'";

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse', -128]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
    }
}
