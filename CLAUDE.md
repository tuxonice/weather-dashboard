# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common commands

Everything runs inside the `app` container — the host does not need PHP. The `Makefile` is the entrypoint.

| Task | Command |
| --- | --- |
| Build images + install vendor | `make build && make composer-install` |
| Start the stack (http://localhost:8080) | `make start` |
| Stop the stack | `make stop` |
| Tail logs | `make logs` |
| Shell into the app container | `make shell` |
| Run the full PHPUnit suite | `make test` |
| Run a single test class | `docker compose run --rm app vendor/bin/phpunit tests/Service/AwarenessLevelTest.php` |
| Filter by test name | `docker compose run --rm app vendor/bin/phpunit --filter testSeverityOrdering` |
| Arbitrary composer command | `make composer CMD="require foo/bar"` |

There is no JS/CSS build step — Bootstrap 5 and Bootstrap Icons are loaded from a CDN by `templates/base.html.twig`.

## Cache

`var/cache/ipma/` holds PSR-16 filesystem cache entries for IPMA API responses (1 h default TTL, see `IpmaConnectorFactory`). When upstream data looks stale or you need to retest a network failure path, delete that directory. `var/cache/twig/` is only populated in non-debug environments.

## Architecture

This is **not a Symfony application** — it is a hand-wired micro-framework that composes individual Symfony components (`http-kernel`, `routing`, `dependency-injection`, `event-dispatcher`, `translation`, `twig-bridge`, `cache`). There is no `Symfony\Bundle\FrameworkBundle`, no `AbstractController`, no auto-configuration, and no attribute routing. Three files own the wiring:

- **`public/index.php`** — front controller. Builds the kernel, dispatches one request.
- **`src/Kernel.php`** — assembles the `HttpKernel`: container, `UrlMatcher` from `RouteLoader`, the three event subscribers (`RouterListener`, `LocaleSubscriber`, `ExceptionSubscriber`), `ContainerControllerResolver`, `ArgumentResolver`.
- **`src/Framework/ContainerFactory.php`** — single source of truth for the DI graph. Every controller, repository, Twig extension, the translator, and the IPMA connector is registered here in plain PHP. **Adding a new repository or controller means editing this file** — there is no autowiring or autoconfiguration.
- **`src/Framework/RouteLoader.php`** — single source of truth for routes. Routes are plain `Route` objects added to a `RouteCollection`. **Adding a new endpoint means adding a `$routes->add(...)` here.**

Controllers are registered as **public** services so `ContainerControllerResolver` can pull them by FQCN. They are constructed with their dependencies (Twig + repositories) via the container — no `extends` from a base class. They render templates by calling `$this->twig->render(...)` and wrapping the string in a `Response`.

### Request lifecycle

1. Nginx forwards everything to `public/index.php` via PHP-FPM (see `docker/nginx.conf`).
2. `Kernel::handle()` dispatches through `HttpKernel`.
3. `RouterListener` (priority 32) matches against `RouteLoader`'s collection, populating the `_locale` route attribute (every page route is registered per-locale, e.g. `home.pt` / `home.en`).
4. `LocaleSubscriber` (priority 16, runs *after* `RouterListener`) reads the locale from that `_locale` attribute, falling back to the default `pt` for the prefix-less root `/`. It sets the locale on the request, the translator, the routing `RequestContext` (so `path()` emits locale-prefixed URLs), and as Twig globals (`app_locale`, `app_supported_locales`, `app_current_route`, `app_route_params`). There is no cookie or `?lang=` handling — locale lives entirely in the URL prefix. The bare `/` is a 302 redirect to `/pt` via `HomeController::redirect`.
5. `ContainerControllerResolver` fetches the controller service; the action runs.
6. `ExceptionSubscriber` (priority -8) catches everything not handled upstream:
   - `HttpExceptionInterface` → `templates/error/{status}.html.twig` (falls back to `error.html.twig`).
   - `Tlab\IpmaApi\Exception\IpmaApiException` → 502 with `templates/error/upstream.html.twig`.
   - Anything else → 500. **In debug mode, non-HTTP exceptions are *not* caught** so Symfony's built-in renderer can show the trace.

### Data layer

The IPMA Open API is reached via the `tuxonice/ipma-api` package, wrapped by `IpmaConnectorFactory` in a `CachedApiConnector` over a PSR-16 filesystem adapter. Repositories across `src/Service/` (organized by feature area: `Forecast/`, `Observation/`, `Services/`) are thin façades — they call `IpmaService::createXyzApi($connector)->query()->...` and return DTOs from the upstream package or small app-side value objects (`DailyForecast`, `SeaStateDay`, `StationObservation`, ...). Controllers should never talk to `IpmaService` directly; add a repository.

`IpmaApiException` is the upstream's failure type. Some controllers catch it locally to render a partially-degraded page (e.g. `LocationController::show` keeps rendering the location even if the warnings fetch fails), while letting full-page failures bubble up to `ExceptionSubscriber`. Non-critical sub-fetches should swallow it; primary fetches should let it propagate.

### Static reference data

`tuxonice/ipma-api` does not yet wrap a few IPMA reference endpoints (weather types, wind classes, precipitation classes), so `App\Service\WeatherDictionary` carries frozen copies. Other static helpers are organized by feature across `src/Service/`:
- `Forecast/Meteorology/`: `FireRiskLevel`, `UvIndexLevel`
- `Forecast/Warnings/`: `AwarenessLevel`
- `Observation/Biology/`: `BivalveStatus`
- `Observation/Climate/`: `PdsiLevel`
- `Observation/Meteorology/`: `StationWindDirection`
- `Observation/Seismic/`: `SeismicMagnitudeLevel`
- Root level: `TemperatureBand`, `MunicipalitySlug`

These helpers classify IPMA codes into Bootstrap colour/icon metadata.

**Convention:** these helpers return **translation keys** (e.g. `awareness.level.yellow`), not human strings. Templates resolve them with `|trans`. Do not return finished labels — keep them as keys so PT/EN both work.

### Templating & i18n

- Per-page templates live in `templates/<feature>/`, all extend `templates/base.html.twig`.
- Translation catalogues are YAML in `translations/messages.{pt,en}.yaml`. Default locale is `pt`, fallback `en`. `TranslatorFactory` auto-discovers any `messages.<locale>.yaml` (and other domains following `<domain>.<locale>.yaml`).
- The language switcher in `base.html.twig` is a dropdown of plain links to the *same* logical route in the other locale (it strips the `.pt`/`.en` suffix from `app_current_route`, re-appends the target locale, and reuses `app_route_params` minus `_locale`). Matching `<link rel="alternate" hreflang>` tags are emitted in `<head>` the same way. Switching language just navigates to the locale-prefixed URL.

### Tests

`phpunit.xml` runs the `tests/` directory as a single `unit` suite with `failOnWarning` and `failOnRisky` enabled. Existing coverage is **only for the static domain helpers** under `src/Service/` (level classifiers, wind direction, weather dictionary, municipality slug, repository unit tests with stubs). Controllers, the kernel, and live IPMA-calling repositories are not tested. When adding tests, follow the same pattern: pure functions over real upstream calls, stub `ApiConnectorInterface` if you must touch a repository.

## Adding things — the wiring checklist

Because there is no autowiring, a new feature usually touches five files:

1. Write the repository / service in `src/Service/` organized by feature area (e.g., `src/Service/Forecast/Meteorology/`, `src/Service/Observation/Climate/`).
2. Write the controller in `src/Controller/` following the same feature organization (e.g., `src/Controller/Forecast/Meteorology/`, `src/Controller/Observation/Climate/`).
3. Register both the service (and its controller) in `src/Framework/ContainerFactory.php`. Controllers must be `setPublic(true)`.
4. Add the route in `src/Framework/RouteLoader.php` (path, controller method, `requirements`, methods).
5. Add translation keys to **both** `translations/messages.pt.yaml` and `messages.en.yaml`.
6. Create the template under `templates/<feature>/` extending `base.html.twig`; use `{% set active = '<nav-key>' %}` if it should highlight a nav entry.

## Notes on upstream quirks

`docu/plan.md` documents IPMA endpoint quirks. Check there before assuming an IPMA failure is your bug.