<?php

declare(strict_types=1);

namespace App\Framework;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Overrides the Twig `path()` and `url()` functions to automatically append
 * the current locale suffix to route names.
 *
 * Routes are registered as "home.pt" / "home.en", but templates call
 * path('home', {...}) — this extension rewrites the call to
 * path('home.' ~ currentLocale, {...}) transparently.
 *
 * Must be registered AFTER RoutingExtension so it takes precedence.
 */
final class LocalizedRoutingExtension extends AbstractExtension
{
    private string $locale = LocaleSubscriber::DEFAULT;

    public function __construct(private readonly UrlGeneratorInterface $generator)
    {
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('path', $this->path(...)),
            new TwigFunction('url', $this->url(...)),
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function path(string $name, array $parameters = [], bool $relative = false): string
    {
        $localized = $this->localizedName($name);

        return $this->generator->generate(
            $localized,
            $this->translateParameters($localized, $parameters),
            $relative
                ? UrlGeneratorInterface::RELATIVE_PATH
                : UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function url(string $name, array $parameters = [], bool $relative = false): string
    {
        $localized = $this->localizedName($name);

        return $this->generator->generate(
            $localized,
            $this->translateParameters($localized, $parameters),
            $relative
                ? UrlGeneratorInterface::NETWORK_PATH
                : UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    /**
     * Translates route parameters whose URL representation is locale-specific.
     * Handles the `day` parameter on routes that expose forecast-day URLs:
     * callers pass the internal key (today | tomorrow | day-after) and we
     * substitute the slug for the target route's locale.
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function translateParameters(string $localizedName, array $parameters): array
    {
        if (!isset($parameters['day']) || !is_string($parameters['day'])) {
            return $parameters;
        }
        if (!preg_match('/^(?:outlook_index|fire_risk_index|warnings_index)\.(\w+)$/', $localizedName, $m)) {
            return $parameters;
        }

        $targetLocale = $m[1];
        $segmentName = match ($parameters['day']) {
            'tomorrow'  => 'day_tomorrow',
            'day-after' => 'day_day_after',
            default     => null,
        };
        if ($segmentName === null) {
            return $parameters;
        }

        $parameters['day'] = RouteLoader::segment($segmentName, $targetLocale);

        return $parameters;
    }

    private function localizedName(string $name): string
    {
        // Already suffixed (e.g. from the lang switcher: 'home.en') — leave as-is.
        foreach (RouteLoader::SUPPORTED_LOCALES as $loc) {
            if (str_ends_with($name, ".$loc")) {
                return $name;
            }
        }

        // Special routes with no locale (root redirect).
        if ($name === 'root') {
            return $name;
        }

        return $name . '.' . $this->locale;
    }
}
