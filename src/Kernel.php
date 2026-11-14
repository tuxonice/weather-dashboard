<?php

declare(strict_types=1);

namespace App;

use App\Framework\ContainerFactory;
use App\Framework\ExceptionSubscriber;
use App\Framework\LocaleSubscriber;
use App\Framework\SecurityHeadersSubscriber;
use App\Framework\RouteLoader;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\HttpKernel\EventListener\RouterListener;

/**
 * Minimal application kernel.
 *
 * Wires a Symfony HttpKernel on top of:
 *  - symfony/routing      (URL -> controller mapping)
 *  - symfony/http-kernel  (controller resolution, argument resolution, events)
 *  - symfony/dependency-injection (service container)
 *  - twig/twig            (templating, via TwigBridge)
 */
final class Kernel implements HttpKernelInterface, TerminableInterface
{
    private ContainerInterface $container;
    private HttpKernel $httpKernel;

    public function __construct(
        private readonly string $environment = 'prod',
        private readonly bool $debug = false,
    ) {
        $this->container = ContainerFactory::create(
            projectDir: dirname(__DIR__),
            environment: $this->environment,
            debug: $this->debug,
        );

        $routes = RouteLoader::load();
        /** @var RequestContext $context */
        $context = $this->container->get('router.request_context');
        $matcher = new UrlMatcher($routes, $context);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener($matcher, new \Symfony\Component\HttpFoundation\RequestStack()));
        $dispatcher->addSubscriber(new LocaleSubscriber(
            $this->container->get('translator'),
            $this->container->get('twig'),
            $this->container->get('router.request_context'),
            $this->container->get('twig.extension.localized_routing'),
        ));
        $dispatcher->addSubscriber(new SecurityHeadersSubscriber());
        $dispatcher->addSubscriber(new ExceptionSubscriber(
            $this->container->get('twig'),
            $this->debug,
        ));

        $controllerResolver = new ContainerControllerResolver($this->container);
        $argumentResolver = new ArgumentResolver();

        $this->httpKernel = new HttpKernel(
            $dispatcher,
            $controllerResolver,
            new \Symfony\Component\HttpFoundation\RequestStack(),
            $argumentResolver,
        );
    }

    public function handle(
        Request $request,
        int $type = self::MAIN_REQUEST,
        bool $catch = true,
    ): Response {
        return $this->httpKernel->handle($request, $type, $catch);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->httpKernel->terminate($request, $response);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
