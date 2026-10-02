# Changelog

All notable changes to `google-places-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

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
- Distance and travel time through the Routes API (`distance()`, including round trips) and a full
  origins × destinations matrix (`GooglePlaces::matrix()`).
- Photo URLs and photo bytes (`GooglePlaces::photo()`), with the API key sent as a header and never
  exposed.
- A `PlacesException` with typed accessors (`isDenied()`, `isRateLimited()`, `googleReason()`, …);
  the API key never appears in messages, events, logs or cache keys — every message, including a
  connection failure's (which ends in the request URL), is redacted.
- Optional response caching, opt-in request logging and lifecycle events
  (`PlacesResponseReceived`, `PlacesRequestFailed`).
- `ValidPlaceId` and `ValidCoordinates` validation rules, and the `google-places:check` command to
  verify your key and enabled APIs.
- A `google_places` driver for geolocation-for-laravel, and per-API client-side rate limiting.
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
