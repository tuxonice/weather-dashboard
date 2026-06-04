# Planet Position Calculation

This document explains how the planet positions displayed on the location detail page are computed.

---

## Overview

The implementation uses **simplified astronomical algorithms** based on Jean Meeus' *Astronomical Algorithms* (2nd edition, 1998). The positions are computed from mean orbital elements and low-precision perturbation theory, sufficient for naked-eye observation planning. Results are accurate to within a few arcminutes for coordinates and within ~0.1 magnitude for brightness.

---

## Planets Covered

All seven classical planets are included:

| Planet | Symbol | Typical Magnitude | Notes |
|--------|--------|-------------------|-------|
| **Mercury** | ☿ | −2 to +6 | Never far from Sun; best seen at elongation |
| **Venus** | ♀ | −4.6 to −3.0 | Brightest planet; morning/evening star |
| **Mars** | ♂ | −2.9 to +2.0 | Reddish color; variable brightness |
| **Jupiter** | ♃ | −2.9 to −1.6 | Largest planet; naked-eye moons possible |
| **Saturn** | ♄ | +0.5 to +1.5 | Rings visible in binoculars |
| **Uranus** | ♅ | +5.5 to +6.0 | Barely naked-eye; binoculars recommended |
| **Neptune** | ♆ | +7.8 to +8.0 | Telescope required |

---

## Coordinate Systems

Two coordinate systems are provided:

### Equatorial Coordinates (RA/Dec)

- **RA (Right Ascension)** — Celestial longitude measured in hours (0–24h). Analogous to longitude on Earth.
- **Dec (Declination)** — Celestial latitude measured in degrees (−90° to +90°). Analogous to latitude on Earth.

These are **independent of observer location** and are the standard coordinates used in star charts and ephemerides.

### Horizontal Coordinates (Az/Alt)

- **Azimuth** — Compass bearing from observer (0° = North, 90° = East, 180° = South, 270° = West).
- **Altitude** — Angle above the horizon (−90° to +90°). Positive = visible, negative = below horizon.

These are **observer-dependent** and determine whether a planet is currently visible from a given location.

---

## The Position Algorithm

### Step 1: Julian Day

All calculations use **Julian Day Number (JD)**, a continuous count of days since noon UTC on 1 January 4713 BCE:

```
T = (JD − 2451545.0) / 36525.0   # Julian centuries since J2000.0 epoch
```

### Step 2: Heliocentric Position

For each planet, mean orbital elements are evaluated at time `T`:

```
a  = semimajor axis (AU)
e  = eccentricity
i  = inclination to ecliptic (°)
L  = mean longitude (°)
ω  = argument of perihelion (°)
Ω  = longitude of ascending node (°)
```

These are updated with linear secular rates to give the planet's heliocentric (Sun-centered) position in rectangular coordinates (x, y, z) and spherical (longitude, latitude, distance).

### Step 3: Geocentric Transformation

The planet's position relative to Earth is obtained by vector subtraction:

```
x_geo = x_planet − x_earth
y_geo = y_planet − y_earth
z_geo = z_planet − z_earth
```

This gives the planet's geocentric ecliptic coordinates.

### Step 4: Equatorial Conversion

Ecliptic coordinates are converted to equatorial using the obliquity of the ecliptic (ε ≈ 23.44°):

```
RA  = atan2(sin(λ)·cos(ε) − tan(β)·sin(ε), cos(λ))
Dec = asin(sin(β)·cos(ε) + cos(β)·sin(ε)·sin(λ))
```

Where λ = ecliptic longitude, β = ecliptic latitude.

### Step 5: Horizontal Conversion

The final step converts to observer-local horizontal coordinates:

```
LST = local sidereal time (computed from JD and observer longitude)
H   = LST − RA                    # Hour angle
Alt = asin(sin(Dec)·sin(φ) + cos(Dec)·cos(φ)·cos(H))
Az  = atan2(sin(H), cos(H)·sin(φ) − tan(Dec)·cos(φ))
```

Where φ = observer latitude. Azimuth is normalized to 0–360° with 0° = North.

In code (`PlanetPosition::getPlanetPosition`):

```php
$jd = self::toJulianDay($when);
$T  = ($jd - 2451545.0) / 36525.0;

$heliocentric = self::getHeliocentricPosition($planet, $T);
$earthHelio   = self::getHeliocentricPosition('earth', $T);
$geocentric   = self::heliocentricToGeocentric($heliocentric, $earthHelio);
$equatorial   = self::toEquatorial($geocentric);
$horizontal   = self::toHorizontal($hourAngle, $equatorial['dec'], $observerLat);
```

---

## Distance and Magnitude

### Distance

The geocentric distance is the magnitude of the geocentric vector:

```
distance_au = √(x² + y² + z²)        # In astronomical units
distance_km = distance_au × 149597870.7
```

1 AU ≈ 149.6 million km is the mean Earth-Sun distance.

### Apparent Magnitude

The brightness of a planet depends on:
- Its distance from Sun (`r`)
- Its distance from Earth (`Δ`)
- Its phase angle (Sun-Earth-Planet angle)

A simplified magnitude formula is used for each planet:

```
Mercury: −0.42 + 5·log₁₀(r·Δ) + 0.038·phase − 0.000273·phase²
Venus:   −4.00 + 5·log₁₀(r·Δ) + 0.013·phase + 0.000000·phase²
Mars:    −1.52 + 5·log₁₀(r·Δ) + 0.016·phase
Jupiter: −9.40 + 5·log₁₀(r·Δ) + 0.005·phase
Saturn:  −8.88 + 5·log₁₀(r·Δ) + 0.044·phase + 0.5·(1 − cos(phase))
Uranus:  −7.19 + 0.002·phase + 5·log₁₀(r·Δ)
Neptune: −6.87 + 0.001·phase + 5·log₁₀(r·Δ)
```

Where `phase` is the Sun-Earth-Planet angle (0° = full phase, 180° = new phase).

---

## Elongation

**Elongation** is the angular separation between the planet and the Sun as seen from Earth:

```
cos(E) = (r² + Δ² − R²) / (2·r·Δ)
```

Where `r` = planet-Sun distance, `Δ` = planet-Earth distance, `R` = Earth-Sun distance.

- **0°** = Planet behind Sun (conjunction, invisible)
- **90°** = Quadrature (maximum elongation for outer planets)
- **180°** = Opposition (outer planets opposite Sun, best visibility)

For Mercury and Venus, maximum elongation occurs when the Sun-Earth-Planet angle is greatest; they are never seen at opposition.

---

## Rise, Set, and Transit Times

For planning observations, the service can compute approximate rise, set, and transit (culmination) times:

### Algorithm

1. Sample planet altitude every 3 hours across a 48-hour window centered on local noon
2. Detect sign changes in altitude (crossing horizon)
3. Linearly interpolate between samples to estimate exact crossing time
4. Transit time is estimated as midpoint between rise and set

This is a **low-precision approximation** suitable for planning purposes only. It does not account for:
- Atmospheric refraction (~34′ at horizon)
- Local horizon obstructions
- Parallax (significant for Moon, negligible for planets)

Accuracy is typically ±10–20 minutes.

---

## Visibility Flag

A planet is marked **visible** when its altitude is strictly greater than 0° (above the horizon). This is a geometric calculation ignoring:

- Atmospheric extinction (dimming near horizon)
- Twilight sky brightness
- Local terrain

For practical observation, planets need altitude > 10–15° above horizon in dark skies.

---

## Accuracy and Limitations

| Factor | Typical Error | Notes |
|--------|---------------|-------|
| Position (RA/Dec) | ±5–10 arcminutes | Sufficient for finder scopes |
| Distance | ±0.01 AU | ~1% for inner planets, better for outer |
| Magnitude | ±0.2 mag | Phase function simplification |
| Rise/set times | ±15 min | Linear interpolation; no refraction |
| Coordinate frames | J2000.0 | No precession to current epoch |
| Perturbations | Omitted | No corrections for Jupiter-Saturn interactions |

The implementation is intended as an **indicative guide** for casual observation planning, not for precise astronomical measurements or telescope pointing.

---

## Code Structure

The service is a **pure static helper** (like `MoonPhase`) with no dependencies:

```php
// Get all planet positions for a location
$positions = PlanetPosition::getAllPlanetPositions($when, $lat, $lon);

// Get only currently visible planets, sorted by brightness
$visible = PlanetPosition::getVisiblePlanets($when, $lat, $lon);

// Get rise/set/transit times for a specific planet
$times = PlanetPosition::getRiseSetTimes('jupiter', $date, $lat, $lon, $tz);
```

Each position array contains:
- `id`, `name`, `emoji`, `translation_key` — Display metadata
- `ra`, `dec` — Equatorial coordinates (degrees)
- `ra_hms`, `dec_dms` — Formatted RA/Dec (h:m:s, d:m:s)
- `azimuth`, `altitude` — Horizontal coordinates (degrees)
- `distance_au`, `distance_km` — Distance from Earth
- `magnitude` — Apparent brightness
- `elongation` — Angular separation from Sun
- `is_visible` — Above horizon flag
- `is_morning` — Before transit (rising side of sky)
