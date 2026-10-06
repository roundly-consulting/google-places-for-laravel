# Changelog

All notable changes to `google-places-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.1.0 - 2026-10-06

### Changed

- Requires geolocation-for-laravel `^2.0`. If your app uses geolocation's Google distances, enable
  the Routes API on that key.
- Documentation: the README hero image loads from an absolute URL, so it renders on Packagist and
  other sites.
- Maintenance: `composer.json` `homepage` and `support.docs` point at the package documentation
  site.

### Fixed

- `GooglePlaces::autocomplete()` skips a malformed non-object suggestion instead of throwing a
  `TypeError`.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- A typed client for Google's Places API (New), Routes API and Geocoding API, available as the
  `GooglePlaces` facade or the `PlacesClient` contract, built on Laravel's HTTP client.
- Place autocomplete with billing sessions (`GooglePlaces::session()`), and place details with
  per-request field masks.
- Text search, nearby search and find place, with auto-pagination (`all()`, `cursor()`) and
  location-bias helpers (`nearby()`, `withinBounds()`).
- Reverse and forward geocoding (`geocode()`, `geocodeAddress()`) with typed address components
  such as `street()`, `city()`, `postalCode()` and `countryCode()`.
- Distance and travel time through the Routes API (`distance()`: one origin to one destination, or
  to several as `MultipleDistances` — one separate trip each) and a full origins × destinations
  matrix (`GooglePlaces::matrix()`).
- Photo URLs and photo bytes (`GooglePlaces::photo()`), with the API key sent as a header and never
  exposed.
- A `PlacesException` with typed accessors (`isDenied()`, `isRateLimited()`, `isUnreachable()`,
  `googleReason()`, …);
  the API key never appears in messages, events, logs or cache keys — every message, including a
  connection failure's (which ends in the request URL), is redacted.
- Optional response caching, opt-in request logging and lifecycle events
  (`PlacesResponseReceived`, `PlacesRequestFailed`).
- `ValidPlaceId` and `ValidCoordinates` validation rules, and the `google-places:check` command to
  verify your key and enabled APIs.
- A `google_places` driver for geolocation-for-laravel that never aborts the host's pipeline (an
  unreachable Google is a `ProviderUnavailableException`, any other Places failure a miss), and
  per-API client-side rate limiting.
- `GooglePlaces::fake()` for testing without real HTTP.
- `GooglePlaces::check()` returns one `ApiCheckResult` per Google API (Places, Routes, Geocoding)
  for health endpoints and deploy gates; `google-places:check` is a thin wrapper over it. The key
  is redacted from every detail, including connection errors.
- `GooglePlaces::photoUri()` and `GooglePlaces::photoContents()` — the key-free photo URL and the
  photo bytes, straight from the client.
- Fake: `withPhoto(string $bytes, ?string $uri = null)`, `withCheck(list<ApiCheckResult>)`,
  `assertPhotoRequested(name|Closure(PhotoQuery))` and `assertChecked()`.

### Changed

- Photo requests (`photo()->url()/contents()/save()`) go through the `PlacesClient`: they are
  throttled on the `places` budget, emit `PlacesResponseReceived`/`PlacesRequestFailed`
  (endpoints `photoUri`/`photoContents`) and are recorded by `GooglePlaces::fake()` instead of
  reaching Google.
- `session()`, `matrix()` and `photo()` moved from facade statics onto the `PlacesClient` contract,
  so injected clients have them too; the contract also gains `photoUri()`, `photoContents()` and
  `check()`. Facade calls are unchanged.
- The fake class is renamed `Testing\FakePlacesClient` → `Testing\GooglePlacesFake`.
- Outbound rate limits are built through the `RateLimits` facade of
  http-client-rate-limits-for-laravel.
- A boolean switch that isn't `true`/`false`/`1`/`0`/`on`/`off`/`yes`/`no` now throws
  `InvalidConfigurationException` naming its full key, instead of falling back to the default.
- Every other setting is read strictly too, so an env typo no longer turns into a silent default:
  `(int)` turned `GOOGLE_PLACES_TIMEOUT=five` into no timeout and a junk `max_pages` into one
  page, a `per` typo became a minute, a junk `max_wait` / `jitter` was dropped and a non-string
  host, field mask, cache store or log channel was swapped for a default. Integers must be
  integers in range (timeouts, `cache.ttl`, `max_pages` and `limit` at least 1), `per` one of
  the four windows and every string setting a string; a key that is not set keeps its default.
- Blank means not set: a blank value (a host's `KEY=`, empty or whitespace only) reads exactly
  like an absent key. A blank host, cache store, log channel or rate-limit owner takes its
  default, a blank `max_wait` / `jitter` is unset (a blank `max_wait` used to become a 0 ms
  fail-fast ceiling), a blank field mask throws "required but missing", and a whitespace-only
  API key counts as missing.
