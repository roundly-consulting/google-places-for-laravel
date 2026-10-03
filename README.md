<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/google-places-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel">
    <img src="art/hero.png" alt="Google Places for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/google-places-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/google-places-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/google-places-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/google-places-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/google-places-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/google-places-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Google Places for Laravel

Query Google's **Places API (New)**, **Routes API**, and **Geocoding API** — place details,
autocomplete, text & nearby search, find place, reverse geocoding, distance/ETA, and photo
URLs — from Laravel through a small, fully typed client with expressive query objects and data
transfer objects.

Built entirely on Laravel's own HTTP client, with header authentication
(`X-Goog-Api-Key`), required field masks, configurable timeouts/retries, optional response
caching, lifecycle events, and a first-class `GooglePlaces::fake()` testing helper.

## Integrates with

This package builds on four lower-tier roundly-consulting packages (its only runtime
dependencies besides Laravel):

- **[package-toolkit-for-laravel](https://github.com/roundly-consulting/package-toolkit-for-laravel)** —
  the service provider is built on the toolkit's package builder (config, commands, publish
  tags, an `about` section), and the rate-limit exception carries the toolkit's
  `HasRetryAfter` contract.
- **[enums-for-laravel](https://github.com/roundly-consulting/enums-for-laravel)** — the
  `TravelMode` enum gains `values()`, `labels()`, `options()`, `validationRule()`,
  `readable()`, and case lookups on top of its own `routesValue()`.
- **[geolocation-for-laravel](https://github.com/roundly-consulting/geolocation-for-laravel)** —
  Google Places registers itself as a `google_places` geolocation driver, so a host running
  geolocation can forward-/reverse-geocode through Google Places. See
  [Geolocation driver](#geolocation-driver).
- **[http-client-rate-limits-for-laravel](https://github.com/roundly-consulting/http-client-rate-limits-for-laravel)** —
  every outbound call is paced per Google API surface. See [Rate limiting](#rate-limiting).

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`
- A Google Maps Platform API key with the **Places API (New)**, **Routes API**, and
  **Geocoding API** enabled

## Installation

```bash
composer require roundly-consulting/google-places-for-laravel
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="google-places-config"
```

## Configuration

Set your key in `.env`:

```dotenv
GOOGLE_PLACES_API_KEY=your-google-maps-api-key
```

The package works with zero extra configuration once the key is set. Every value lives in
`config/google-places.php` and is read strictly: only an unset (`null`) key takes its default,
and anything invalid throws `InvalidConfigurationException` naming the key, never a silent
fallback.

- A `bool` switch accepts `true`/`false`, `1`/`0`, `on`/`off` or `yes`/`no`, from `.env` or the
  published file.
- An `int` takes an integer or an integer string (`'30'`). `'five'`, `'5.5'`, `'5s'`, a blank env
  or a value out of range throws. Timeouts, `cache.ttl`, `pagination.max_pages` and the
  rate-limit `limit` must be at least 1. Retries, `retry_delay`, `max_wait` and `jitter` must be
  at least 0.
- `rate_limits.{surface}.per` must be exactly `second`, `minute`, `hour` or `day`.
- A string (`hosts.*`, `field_masks.*`, `cache.store`, `logging.channel`, `rate_limits.owner`)
  must be non-empty. To use the default store or channel, leave it unset rather than blank.

| Key | Type | Default | Env | Purpose |
|---|---|---|---|---|
| `key` | `string` | `null` | `GOOGLE_PLACES_API_KEY` | API key. Sent as `X-Goog-Api-Key` (Places/Routes) and `key=` (Geocoding). Required. |
| `hosts.places` | `string` | `https://places.googleapis.com/v1` | `GOOGLE_PLACES_HOST` | Places API (New) host. |
| `hosts.routes` | `string` | `https://routes.googleapis.com` | `GOOGLE_ROUTES_HOST` | Routes API host (distance/ETA). |
| `hosts.geocoding` | `string` | `https://maps.googleapis.com/maps/api` | `GOOGLE_GEOCODING_HOST` | Geocoding API host (reverse geocoding). |
| `field_masks.details` | `string` | see config | — | `X-Goog-FieldMask` for place details. |
| `field_masks.autocomplete` | `string` | see config | — | `X-Goog-FieldMask` for autocomplete. |
| `field_masks.search` | `string` | see config | — | `X-Goog-FieldMask` for text/nearby search. |
| `field_masks.routes` | `string` | see config | — | `X-Goog-FieldMask` for the route matrix. |
| `http.timeout` | `int` (≥ 1) | `10` | `GOOGLE_PLACES_TIMEOUT` | Request timeout (seconds). |
| `http.connect_timeout` | `int` (≥ 1) | `5` | `GOOGLE_PLACES_CONNECT_TIMEOUT` | Connection timeout (seconds). |
| `http.retries` | `int` (≥ 0) | `2` | `GOOGLE_PLACES_RETRIES` | Retries on connection failure. |
| `http.retry_delay` | `int` (≥ 0) | `200` | `GOOGLE_PLACES_RETRY_DELAY` | Delay between retries (ms). |
| `cache.enabled` | `bool` | `false` | `GOOGLE_PLACES_CACHE` | Cache idempotent lookups (details/geocode/distance/search/matrix). |
| `cache.store` | `?string` | `null` | `GOOGLE_PLACES_CACHE_STORE` | Cache store (null = default). |
| `cache.ttl` | `int` (≥ 1) | `86400` | `GOOGLE_PLACES_CACHE_TTL` | Cache TTL (seconds). |
| `pagination.max_pages` | `int` (≥ 1) | `5` | `GOOGLE_PLACES_MAX_PAGES` | Safety cap for the paginated search helpers; a warning is logged when hit. |
| `logging.enabled` | `bool` | `false` | `GOOGLE_PLACES_LOGGING` | Log every request (endpoint, status, duration). The API key is never logged. |
| `logging.channel` | `?string` | `null` | `GOOGLE_PLACES_LOG_CHANNEL` | Log channel to write to (null = default channel). |
| `rate_limits.owner` | `string` | `app` | `GOOGLE_PLACES_RATELIMIT_OWNER` | Bucket owner, shared across surfaces (`google-places:{surface}:{owner}`). |
| `rate_limits.{surface}.enabled` | `bool` | `true` | `GOOGLE_PLACES_{SURFACE}_RATELIMIT_ENABLED` | Throttle this surface (`places`/`routes`/`geocoding`); `false` = unthrottled. |
| `rate_limits.{surface}.limit` | `int` (≥ 1) | `600` | `GOOGLE_PLACES_{SURFACE}_RATELIMIT` | Max requests per window. |
| `rate_limits.{surface}.per` | `string` | `minute` | `GOOGLE_PLACES_{SURFACE}_RATELIMIT_PER` | Window: `second`, `minute`, `hour`, `day`. |
| `rate_limits.{surface}.adaptive` | `bool` | `true` | `GOOGLE_PLACES_{SURFACE}_RATELIMIT_ADAPTIVE` | Self-tune from a 429 `Retry-After`. |
| `rate_limits.{surface}.max_wait` | `?int` (≥ 0) | `null` | `GOOGLE_PLACES_{SURFACE}_RATELIMIT_MAX_WAIT` | Fail fast (ms) instead of pacing; `null` = pace. |
| `rate_limits.{surface}.jitter` | `?int` (≥ 0) | `null` | `GOOGLE_PLACES_{SURFACE}_RATELIMIT_JITTER` | Random spread (ms) added to a defer. |

> **Field masks:** the Places API (New) and Routes API require an `X-Goog-FieldMask` header
> naming the fields to return. The defaults are conservative; trim them in config so you pay
> only for the fields you use.
>
> **Reverse geocoding** intentionally uses the Geocoding API (authenticated with the `key`
> query parameter), which is the current product for that capability.

## Usage

Everything goes through the `GooglePlaces` facade:

```php
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$place = GooglePlaces::details('ChIJN1t_tDeuEmsRUsoyG83frY4');
$bytes = GooglePlaces::photo($place->photos[0])->contents();
```

### Without the facade

The facade is sugar over the `PlacesClient` contract — inject it and call the same methods.
Both resolve the same singleton, and `GooglePlaces::fake()` replaces both.

```php
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;

final class NearbyCafes
{
    public function __construct(private PlacesClient $places) {}

    public function __invoke(string $query): array
    {
        return $this->places->textSearch($query)->all();
    }
}
```

google-places is a remote-API client, so it has no action classes: the contract's methods are
the use cases.

| Method | Returns |
|---|---|
| `details($query)` | `?Place` |
| `autocomplete($query)` | `Collection<AutocompletePrediction>` |
| `session(?$token)` | `PlacesSession` (`autocomplete()`, `details()`, `token()`) |
| `textSearch($query)`, `nearbySearch($query)` | `Collection<Place>` |
| `textSearchPaginated($query)`, `nearbySearchPaginated($query)` | `SearchPaginator` |
| `findPlace($text, ?$bias)` | `?Place` |
| `geocode($location, ?$lng)`, `geocodeAddress($query)` | `Collection<ReverseGeocodingResult>` |
| `distance(DistanceQuery)` | `Distance\|MultipleDistances` (one trip per destination) |
| `matrix($origins, $destinations)` | `PendingMatrix` (`driving()`, `walking()`, …) |
| `computeMatrix(MatrixQuery)` | `DistanceMatrix` |
| `photo($name, $w, $h)` | `PendingPhoto` (`url()`, `contents()`, `save()`) |
| `photoUri($name, $w, $h)` / `photoContents($name, $w, $h)` | key-free URL / bytes (one request) |
| `photoUrl($name, $w, $h)` | keyed media URL, built locally — server-side only |
| `check()` | `list<ApiCheckResult>` — is each Google API enabled and reachable? |

Common lookups take a scalar shorthand or a full query object for options.

### Autocomplete

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

// String shorthand:
$predictions = GooglePlaces::autocomplete('Luxury Restaurant');

/** @var AutocompletePrediction $prediction */
$prediction = $predictions->first();

echo $prediction->description;
echo $prediction->placeId;
echo $prediction->mainText;       // structured label (nullable)
echo $prediction->secondaryText;  // structured label (nullable)
echo implode(', ', $prediction->types);

// Full query (immutable, fluent):
$query = (new AutocompleteQuery('Coffee'))
    ->ofType('cafe', 'restaurant')               // max 5 included primary types
    ->preferInArea((new LocationDefinition())->circle(new Location(48.1486, 17.1077), radius: 2000))
    ->inRegions('sk')                            // included region codes
    ->fromOrigin(new Location(48.1486, 17.1077))
    ->usingSessionToken('a-session-token');

$predictions = GooglePlaces::autocomplete($query);
```

#### Billing sessions

Tie an autocomplete burst plus the final `details()` call into one Google billing
session. The session reuses a single token automatically and is consumed once
`details()` resolves (calling it again throws a `PlacesException`).

```php
$session = GooglePlaces::session();

$session->autocomplete('piz');
$session->autocomplete('pizza ne');   // same session token
$place = $session->details($placeId); // billed as one session, then closed
```

### Place details

`details()` returns a `Place`, or `null` when the place is not found.

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$place = GooglePlaces::details($prediction->placeId); // or new DetailsQuery(...)

if ($place instanceof Place) {
    echo $place->name;
    echo $place->id;
    echo $place->formattedAddress;
    echo implode(', ', $place->types);

    echo $place->geometry?->location->latitude;
    echo $place->geometry?->location->longitude;

    foreach ($place->openingHours?->periods ?? [] as $period) {
        // from/to are "HHMM" strings; integer accessors are also available.
        echo "{$period->day}: {$period->from}–{$period->to}";
        echo "{$period->openHour}:{$period->openMinute}";
    }

    // Photo resource names — pass to photoUrl().
    foreach ($place->photos as $photoName) {
        $url = GooglePlaces::photoUrl($photoName, maxWidth: 800, maxHeight: 600);
    }

    $raw = $place->raw; // untouched Google payload
}
```

Override the field mask per request by passing field names to `DetailsQuery`:

```php
new DetailsQuery($placeId, fields: ['id', 'displayName', 'location', 'types', 'photos']);
```

### Text & nearby search, find place

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

// Text search → Collection<Place>
$places = GooglePlaces::textSearch('pizza in Bratislava');

$places = GooglePlaces::textSearch(
    (new TextSearchQuery('vegan brunch'))->openNow()->withMinRating(4.0)->take(10),
);

// Nearby search → Collection<Place> (radius required, 0–50000 m)
$places = GooglePlaces::nearbySearch(
    (new NearbySearchQuery(new Location(48.1486, 17.1077), radius: 1500))
        ->withinTypes('cafe')
        ->rankBy('DISTANCE'),
);

// Find a single best match → ?Place
$place = GooglePlaces::findPlace('Eiffel Tower');
```

Text search caps at 60 results; pass a page token to walk a single page manually:

```php
$next = GooglePlaces::textSearch((new TextSearchQuery('pizza'))->withPageToken($token));
```

#### Auto-pagination

Let the package follow `nextPageToken` for you. `all()` eagerly collects every page;
`cursor()` returns a `LazyCollection` that fetches pages on demand. Both honour the
`pagination.max_pages` safety cap (a warning is logged when it's hit).

```php
// Eagerly collect every page into one collection:
$all = GooglePlaces::textSearchPaginated('museums in Bratislava')->all();

// Stream lazily — only fetches the next page when you ask for more places:
GooglePlaces::textSearchPaginated('museums')
    ->cursor()
    ->each(fn ($place) => /* ... */);

// Nearby search returns a single page (the New API does not paginate it):
$all = GooglePlaces::nearbySearchPaginated($nearbyQuery)->all();
```

#### Location-bias helpers

Bias or restrict text search and autocomplete fluently:

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;

(new TextSearchQuery('coffee'))->nearby(48.1486, 17.1077, radius: 2000);              // circle bias
(new TextSearchQuery('coffee'))->withinBounds(new Location(48.1, 17.0), new Location(48.2, 17.2)); // rectangle restriction
```

#### Place helpers

The `Place` DTO ships convenience accessors:

```php
$place->isOpenNow();           // bool
$place->coordinates();         // ?Location
$place->primaryType();         // ?string
$place->openingHoursFor(1);    // list<OpeningHourPeriod> for Monday (0 = Sunday)
```

### Reverse geocoding

`geocode()` returns a collection of `ReverseGeocodingResult` for a coordinate.

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$results = GooglePlaces::geocode(48.1486, 17.1077);   // lat/lng shorthand
// or GooglePlaces::geocode(new Location(48.1486, 17.1077));
// or GooglePlaces::geocode(new ReverseGeocodingQuery(...));

$result = $results->first();

echo $result->address;
echo $result->placeId;

foreach ($result->components as $component) {
    echo "{$component->longName} ({$component->shortName})";
}
```

### Forward geocoding (address → coordinates)

`geocodeAddress()` turns an address string into a collection of results (each with
coordinates, place id, types, and components).

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$results = GooglePlaces::geocodeAddress('1600 Amphitheatre Pkwy');

$first = $results->first();
echo $first->placeId;
echo $first->geometry->location->latitude;

// Full query with region + component filters:
$results = GooglePlaces::geocodeAddress(
    (new GeocodingQuery('Parliament'))->inRegion('sk')->filterBy('country:SK'),
);
```

### Typed address components

Both geocoding and place results expose ergonomic, typed accessors over their raw
address components via `components()`:

```php
$components = GooglePlaces::geocodeAddress('1600 Amphitheatre Pkwy')->first()->components();

echo $components->streetNumber(); // "1600"
echo $components->street();       // "Amphitheatre Parkway"
echo $components->city();         // "Mountain View"
echo $components->state();        // "California"
echo $components->postalCode();   // "94043"
echo $components->country();      // "United States"
echo $components->countryCode();  // "US"
```

On a `Place`, `components()` is populated only when `addressComponents` is included in
the field mask.

### Distance & travel time (Routes API)

`distance()` measures from **one origin**. To a single `Location` it returns a `Distance`. To
`MultipleLocations` it returns `MultipleDistances`: one separate trip from the origin to each
destination (not a chained route, so there is no total). Choose the mode with the `TravelMode`
enum.

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MultipleLocations;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$distance = GooglePlaces::distance(new DistanceQuery(
    from: new Location(48.1486, 17.1077),  // Bratislava
    to: new Location(48.2082, 16.3738),    // Vienna
    type: TravelMode::Driving,             // Driving | Walking | Bicycling | Transit
    departureAt: now()->addHour(),         // optional; enables traffic-aware driving ETAs
));

echo $distance->humanReadableDistance; // e.g. "79.8 km" (formatted by the package)
echo $distance->distanceInMeters;      // e.g. 79800
echo $distance->humanReadableDuration; // e.g. "1h 5m"
echo $distance->durationInSeconds;     // e.g. 3900
echo $distance->type->value;           // "driving"

// One origin, several destinations → MultipleDistances, in destination order:
$fromBratislava = GooglePlaces::distance(new DistanceQuery(
    from: new Location(48.1486, 17.1077),
    to: new MultipleLocations([
        new Location(48.2082, 16.3738),    // Vienna
        new Location(50.0755, 14.4378),    // Prague
    ]),
));

foreach ($fromBratislava->distances as $i => $trip) {
    echo "Bratislava → destination {$i}: {$trip->humanReadableDistance}";
}
```

A single-element `MultipleLocations` still returns a plain `Distance`. For several origins at
once, use [`matrix()`](#full-distance-matrix-mn).

> A destination with no available route raises a `PlacesException`. The Routes API does not return
> human-readable strings, so `humanReadableDistance`/`humanReadableDuration` are formatted by
> the package.

### Full distance matrix (M×N)

For many origins against many destinations, use `matrix()`. The travel-mode method runs
the request and returns a `DistanceMatrix` you can index by pair.

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$matrix = GooglePlaces::matrix(
    origins: [new Location(48.1486, 17.1077), new Location(48.2082, 16.3738)],
    destinations: [new Location(50.0755, 14.4378), new Location(47.4979, 19.0402)],
)->driving();                       // ->driving() / ->walking() / ->bicycling() / ->transit()

$element = $matrix->for(0, 1);      // origin 0 → destination 1
if ($element?->hasRoute()) {
    echo $element->distance->humanReadableDistance;
    echo $element->distance->durationInSeconds;
}

$matrix->origin(0);                 // Collection<MatrixElement> keyed by destination index
$matrix->destination(1);           // Collection<MatrixElement> keyed by origin index

// Traffic-aware driving ETAs:
GooglePlaces::matrix($origins, $destinations)->departingAt(now()->addHour())->driving();
```

### Photos

`photo()` returns a handle on one place photo. Every request goes through the client — throttled
on the `places` budget, reported through the lifecycle events, and intercepted by
`GooglePlaces::fake()`. The API key is sent as a request header and never exposed to the
browser.

```php
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

// $photoName is a photo resource name from a place ($place->photos).
$photo = GooglePlaces::photo($photoName, maxWidth: 800, maxHeight: 600);

$url   = $photo->url();                   // Google's key-free media URL (safe for the browser)
$bytes = $photo->contents();              // raw image bytes
$path  = $photo->save('public', 'p.jpg'); // store on a filesystem disk → returns the path

// The same two requests without the handle:
GooglePlaces::photoUri($photoName, 800, 600);
GooglePlaces::photoContents($photoName, 800, 600);
```

`photoUrl()` builds the media URL **with the key in its query string**, locally and without a
request — use it only server-side, never in HTML.

### Error handling

Failures throw `RoundlyConsulting\GooglePlaces\Exceptions\PlacesException`, with typed
accessors so you can branch on what went wrong. The API key never appears in an exception
message, an event or a cache key: every message — Google's error text and a transport failure's,
which ends in the request URL where the Geocoding API carries its `key=` — is redacted. The
configured key is masked to its last four characters wherever it appears, and so is the value of
any `key=`/`token=`/`signature=` query parameter. (`$e->response` is the raw HTTP response, request
URL included — don't serialize it into reports.)

```php
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

try {
    $predictions = GooglePlaces::autocomplete('Coffee');
} catch (PlacesException $e) {
    if ($e->isRateLimited())      { /* back off */ }
    elseif ($e->isDenied())       { /* check key / billing / enabled APIs */ }
    elseif ($e->isInvalidRequest()) { /* fix the request */ }
    elseif ($e->isUnreachable())  { /* timeout, DNS failure, refused connection */ }

    report($e); // $e->googleStatus(), $e->googleReason(), $e->googleErrorMessage()
}
```

The classification reads every error shape the package talks to — Places API (New)
`{"error": {…}}`, the Routes API's array-wrapped `[{"error": {…}}]`, and the Geocoding API's
top-level `status`. `isDenied()` means the key, billing or the enabled APIs need attention: an
invalid or expired key is denied even though Google files it under `INVALID_ARGUMENT` —
`googleReason()` carries the `google.rpc.ErrorInfo` reason (`API_KEY_INVALID`,
`API_KEY_SERVICE_BLOCKED`, `SERVICE_DISABLED`, …) that tells them apart; the legacy Geocoding
API has none, so it is `null` there. `isInvalidRequest()` is never true for a key problem, and
the client-side `RateLimitExceededException` answers `isRateLimited()` with `true`.

A missing API key throws `PlacesException::missingApiKey()` before any HTTP request is made.

### Events

Every request the client sends to Google dispatches exactly one lifecycle event you can listen
to for logging, metrics, or alerting: `PlacesResponseReceived` for an answer, `PlacesRequestFailed`
for an error or an unreachable API (`httpStatus` `0`). A call that sends nothing dispatches
nothing: a cache hit, `photoUrl()` (built locally), and a call refused before sending — no key
configured, or a client-side rate-limit fail-fast (`RateLimitExceededException`). `check()`
reports through its return value instead, so its probes dispatch no events either. Neither event
carries the API key.

```php
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;

// PlacesResponseReceived: endpoint, httpStatus, googleStatus, durationMs
// PlacesRequestFailed:    endpoint, httpStatus, googleStatus, message
```

### Caching

Set `GOOGLE_PLACES_CACHE=true` to cache idempotent lookups (details, geocode, forward
geocode, distance, distance matrix, text & nearby search). Autocomplete is never cached.
Cache keys never contain the API key.

### Request logging

Opt-in logging of every request is built on the lifecycle events. The API key is never logged — a
failure's message is redacted as described under [Error handling](#error-handling).

```dotenv
GOOGLE_PLACES_LOGGING=true
GOOGLE_PLACES_LOG_CHANNEL=stack   # optional; defaults to the app's default channel
```

> Laravel's own HTTP client events, and tools that record them (Telescope, for example), see each
> request as sent — a Geocoding request URL includes the key. That recording happens in your app,
> outside this package; filter it there.

### Validation rules

Validate place ids and coordinates in your own requests:

```php
use RoundlyConsulting\GooglePlaces\Rules\ValidCoordinates;
use RoundlyConsulting\GooglePlaces\Rules\ValidPlaceId;

$request->validate([
    'place' => ['required', new ValidPlaceId],
    'point' => ['required', new ValidCoordinates], // Location, [lat, lng], or "lat,lng"
]);
```

### Health check

`check()` probes the Places, Routes and Geocoding APIs with the configured key and returns one
`ApiCheckResult` (`api`, `ok`, `detail`) per API — ready for a health endpoint or a deploy
gate. A Geocoding answer of `REQUEST_DENIED` fails even though Google sends it with HTTP 200;
the key is redacted from every `detail`. Probes are not retried, throttled or cached. With no
key configured it throws `PlacesException::missingApiKey()`.

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ApiCheckResult;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$down = array_filter(GooglePlaces::check(), fn (ApiCheckResult $r) => ! $r->ok);
```

The `google-places:check` command prints the same results as a table and exits non-zero when
any API fails:

```bash
php artisan google-places:check
```

## Geolocation driver

When [geolocation-for-laravel](https://github.com/roundly-consulting/geolocation-for-laravel)
is installed, this package auto-registers a `google_places` driver on the geolocation
manager. It forward-geocodes **addresses** and reverse-geocodes **coordinates** through
Google Places, mapping the result onto geolocation's own `Location` DTO. (Google Places has
no IP geolocation, so IP-only lookups fall through to the next provider.)

Like geolocation's own providers, the driver never aborts your pipeline. An unreachable Google
(timeout, DNS failure, refused connection) is reported as geolocation's
`ProviderUnavailableException`, which the manager records on `LocationResolutionFailed` and skips.
Every other Places failure — no key configured, `REQUEST_DENIED`, `OVER_QUERY_LIMIT`, a
client-side rate-limit fail-fast — is a miss (`null`), so the next provider answers. The
`PlacesRequestFailed` event still fires for an API error.

The driver reads google-places' own config (`GOOGLE_PLACES_API_KEY`, timeouts, rate limits).
geolocation's per-call overrides (`withToken('google_places', …)`, `withConfig()`, `withTimeout()`)
do not reach it.

Opt the driver into your geolocation pipeline, or call it ad-hoc:

```php
// config/geolocation.php — consult google_places in your resolution order:
'pipeline' => ['maxmind_database', 'google_places', 'default'],
```

```php
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;
use RoundlyConsulting\Geolocation\Facades\Geolocation;

// Ad-hoc, scoped to the driver for a single call:
$location = Geolocation::provider('google_places')->locateAddress('Bratislava, Slovakia');
$location = Geolocation::provider('google_places')->locateCoordinates(new Coordinates(48.1486, 17.1077));

echo $location?->humanReadable;
echo $location?->countryIsoCode;
```

## Rate limiting

Every outbound call is paced through
[http-client-rate-limits-for-laravel](https://github.com/roundly-consulting/http-client-rate-limits-for-laravel),
on a **per-surface** budget keyed `google-places:{surface}:{owner}` where `surface` is one
of `places`, `routes`, or `geocoding`. Requests wait for their window to free up by default;
with `adaptive` on (the default), a `429 Retry-After` from Google self-tunes the limiter.

Tune each surface in `config/google-places.php` (see the config table) or via env:

```dotenv
GOOGLE_PLACES_GEOCODING_RATELIMIT=300
GOOGLE_PLACES_GEOCODING_RATELIMIT_PER=minute
GOOGLE_PLACES_ROUTES_RATELIMIT_ENABLED=false   # send this surface unthrottled
GOOGLE_PLACES_PLACES_RATELIMIT_MAX_WAIT=2000    # fail fast (ms) instead of pacing
```

When a surface is set to fail fast (`max_wait`) and the wait would exceed it, the call
throws `RoundlyConsulting\GooglePlaces\Exceptions\RateLimitExceededException` — a
`PlacesException` (so existing `catch (PlacesException)` sites keep working) carrying
`$e->surface` and, through the package toolkit's `HasRetryAfter` contract,
`$e->retryAfterSeconds()`:

```php
use RoundlyConsulting\PackageToolkit\Contracts\HasRetryAfter;

try {
    GooglePlaces::textSearch('coffee');
} catch (PlacesException $e) {
    if ($e instanceof HasRetryAfter) {
        return response('Slow down', 429, ['Retry-After' => $e->retryAfterSeconds()]);
    }

    throw $e;
}
```

## Address autocomplete + validation (host recipe)

Google Places pairs naturally with
[addresses-for-laravel](https://github.com/roundly-consulting/addresses-for-laravel): wire
`GooglePlaces::autocomplete()` into your address form and `GooglePlaces::geocodeAddress()`
to normalise the chosen address into structured fields for `HasAddresses`. This is a
host-app recipe (no dependency wiring — the tier DAG keeps addresses below google-places):

```php
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

// While the user types — suggest addresses:
$predictions = GooglePlaces::autocomplete($request->input('q'));

// On submit — normalise the chosen address into structured fields:
$result = GooglePlaces::geocodeAddress($request->input('address'))->first();

$model->addAddress([
    'street'      => $result?->components()->street(),
    'city'        => $result?->components()->city(),
    'postal_code' => $result?->components()->postalCode(),
    'country'     => $result?->components()->countryCode(),
    'latitude'    => $result?->geometry->location->latitude,
    'longitude'   => $result?->geometry->location->longitude,
]);
```

## Testing

Swap the live client for a fake — no `Http::fake()`, no live (paid) calls:

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$fake = GooglePlaces::fake();

$fake->withAutocomplete([new AutocompletePrediction('Café Roma', 'p1', ['cafe'])]);
$fake->withDetails(new Place('Café Roma'));

// ...exercise code that calls the facade...

$fake->assertAutocompleted(fn (AutocompleteQuery $q) => $q->input === 'Café');
$fake->assertDetailsRequested('p1');
$fake->assertNothingGeocoded();
```

Queue helpers: `withAutocomplete`, `withDetails`, `withGeocode`, `withGeocodeAddress`,
`withMatrix`, `withTextSearch`, `withNearbySearch`, `withFindPlace`, `withDistance`,
`withPhotoUrl`, `withPhoto(string $bytes, ?string $uri = null)`, `withCheck(list<ApiCheckResult>)`.
Assertions: `assertAutocompleted`, `assertDetailsRequested`, `assertGeocoded`,
`assertAddressGeocoded`, `assertMatrixComputed`, `assertTextSearched`, `assertNearbySearched`,
`assertFindPlaceRequested`, `assertDistanceRequested`, `assertPhotoRequested(name|Closure(PhotoQuery))`,
`assertChecked`, `assertNothingRequested`, `assertNothingGeocoded`, `assertNothingAutocompleted`.

The fake is a `PlacesClient`, installed behind the facade **and** the container binding, so
constructor-injected clients, `session()`, `matrix()`, `photo()` (including `save()`, which
writes the seeded bytes), the paginated search helpers and the `google-places:check` command all
hit it — nothing reaches Google.

```php
$fake = GooglePlaces::fake()->withPhoto('JPEG-BYTES');

GooglePlaces::photo('places/p1/photos/a')->save('public', 'p1.jpg');

$fake->assertPhotoRequested('places/p1/photos/a');
```

Run the package test suite with:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you discover a security vulnerability, please review
[our security policy](../../security/policy) on how to report it.

## Credits

- [Andrej Mihaliak](https://github.com/mihaliak)

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
