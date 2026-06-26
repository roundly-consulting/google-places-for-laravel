# Changelog

All notable changes to `google-places-for-laravel` will be documented in this file.

## Unreleased

### Added

- `PlacesClient` contract bound as a singleton; the facade resolves it.
- `GooglePlaces::fake()` + `FakePlacesClient` with queue/record/assert helpers.
- Config-driven HTTP resilience (timeout, connect-timeout, retries) and an early
  missing-API-key guard.
- Optional native response caching (opt-in; details/geocode/distance/search). The API key
  never appears in a cache key.
- Richer `PlacesException` with `googleStatus()`/`googleErrorMessage()` and
  `isRateLimited()`/`isDenied()`/`isInvalidRequest()` across the Places, Routes, and Geocoding
  error shapes.
- `TravelMode` enum (driving/walking/bicycling/transit) and immutable, fluent query DTOs.
- Scalar facade shortcuts (`autocomplete('Coffee')`, `details($id)`, `geocode($lat, $lng)`).
- New endpoints: text search, nearby search, find place, place photos, plus place details and
  autocomplete on the new API.
- Lifecycle events `PlacesResponseReceived` and `PlacesRequestFailed` (never carry the API key).

### Changed

- **Targets Google's Places API (New), Routes API, and Geocoding API** — header authentication
  (`X-Goog-Api-Key`), required field masks, and JSON request bodies. The legacy
  `maps.googleapis.com/maps/api` Places web service is no longer used.
- Distance/ETA now uses the Routes API `computeRouteMatrix`; human-readable distance/duration
  strings are formatted by the package (the Routes API does not return them).
- `Distance`/`Roundtrip`/`DistanceQuery` expose `TravelMode` instead of a raw string.
- Fixed the `DetailsQuery` default field bug — details now request `types` and `photos`.
