<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google API Key
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate every request. Places API (New) and the
    | Routes API authenticate with the `X-Goog-Api-Key` header; the Geocoding
    | API keeps the `key` query parameter. Create the key in the Google Cloud
    | console and enable the Places API (New), Routes API, and Geocoding API.
    |
    */

    'key' => env('GOOGLE_PLACES_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Service Hosts
    |--------------------------------------------------------------------------
    |
    | Each Google product lives on its own host. Override these only when you
    | proxy Google through your own gateway.
    |
    */

    'hosts' => [
        'places' => env('GOOGLE_PLACES_HOST', 'https://places.googleapis.com/v1'),
        'routes' => env('GOOGLE_ROUTES_HOST', 'https://routes.googleapis.com'),
        'geocoding' => env('GOOGLE_GEOCODING_HOST', 'https://maps.googleapis.com/maps/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Masks
    |--------------------------------------------------------------------------
    |
    | Places API (New) and the Routes API require an `X-Goog-FieldMask` header
    | naming the fields to return — a request without one errors. Trim these to
    | only the fields you use so you are billed for nothing more.
    |
    */

    'field_masks' => [
        'details' => 'id,displayName,formattedAddress,location,viewport,types,regularOpeningHours,photos',
        'autocomplete' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat,suggestions.placePrediction.types',
        'search' => 'places.id,places.displayName,places.formattedAddress,places.location,places.viewport,places.types,places.regularOpeningHours,places.photos',
        'routes' => 'originIndex,destinationIndex,distanceMeters,duration,condition,status',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Resilience
    |--------------------------------------------------------------------------
    |
    | Bound every outbound call so a slow or flaky Google response can never
    | hang the host request. Connection failures (timeouts, DNS) are retried;
    | Google business errors are surfaced as a PlacesException.
    |
    */

    'http' => [
        'timeout' => (int) env('GOOGLE_PLACES_TIMEOUT', 10),
        'connect_timeout' => (int) env('GOOGLE_PLACES_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('GOOGLE_PLACES_RETRIES', 2),
        'retry_delay' => (int) env('GOOGLE_PLACES_RETRY_DELAY', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Caching
    |--------------------------------------------------------------------------
    |
    | Opt-in caching for the idempotent lookups (details, geocode, distance,
    | text/nearby search). Autocomplete is never cached. The API key never
    | appears in a cache key.
    |
    */

    'cache' => [
        'enabled' => (bool) env('GOOGLE_PLACES_CACHE', false),
        'store' => env('GOOGLE_PLACES_CACHE_STORE'),
        'ttl' => (int) env('GOOGLE_PLACES_CACHE_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Pagination
    |--------------------------------------------------------------------------
    |
    | The paginated search helpers transparently follow Google's
    | `nextPageToken`. `max_pages` is a safety cap so a runaway result set can
    | never make an unbounded number of billed requests; when the cap is hit
    | with more pages still available, a warning is logged and paging stops.
    |
    */

    'pagination' => [
        'max_pages' => (int) env('GOOGLE_PLACES_MAX_PAGES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Opt-in logging of every call (endpoint, HTTP status, Google status, and
    | duration) built on the PlacesResponseReceived / PlacesRequestFailed
    | events. The API key is never logged. Leave `channel` null to use the
    | application's default log channel.
    |
    */

    'logging' => [
        'enabled' => (bool) env('GOOGLE_PLACES_LOGGING', false),
        'channel' => env('GOOGLE_PLACES_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client-side Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Pace outbound calls per Google API surface (places / routes / geocoding)
    | through http-client-rate-limits-for-laravel, each keyed
    | `google-places:{surface}:{owner}`. Requests wait for their window to free
    | up by default; set a surface's `max_wait` (ms) to fail fast with a typed
    | PlacesException instead. With `adaptive` on, a 429 `Retry-After` from
    | Google self-tunes the limiter. Set a surface's `enabled` to false to send
    | it unthrottled. `owner` is shared across surfaces so several apps sharing
    | one key can each carry their own budget.
    |
    */

    'rate_limits' => [

        'owner' => env('GOOGLE_PLACES_RATELIMIT_OWNER', 'app'),

        'places' => [
            'enabled' => (bool) env('GOOGLE_PLACES_PLACES_RATELIMIT_ENABLED', true),
            'limit' => (int) env('GOOGLE_PLACES_PLACES_RATELIMIT', 600),
            'per' => env('GOOGLE_PLACES_PLACES_RATELIMIT_PER', 'minute'),
            'adaptive' => (bool) env('GOOGLE_PLACES_PLACES_RATELIMIT_ADAPTIVE', true),
            'max_wait' => env('GOOGLE_PLACES_PLACES_RATELIMIT_MAX_WAIT'),
            'jitter' => env('GOOGLE_PLACES_PLACES_RATELIMIT_JITTER'),
        ],

        'routes' => [
            'enabled' => (bool) env('GOOGLE_PLACES_ROUTES_RATELIMIT_ENABLED', true),
            'limit' => (int) env('GOOGLE_PLACES_ROUTES_RATELIMIT', 600),
            'per' => env('GOOGLE_PLACES_ROUTES_RATELIMIT_PER', 'minute'),
            'adaptive' => (bool) env('GOOGLE_PLACES_ROUTES_RATELIMIT_ADAPTIVE', true),
            'max_wait' => env('GOOGLE_PLACES_ROUTES_RATELIMIT_MAX_WAIT'),
            'jitter' => env('GOOGLE_PLACES_ROUTES_RATELIMIT_JITTER'),
        ],

        'geocoding' => [
            'enabled' => (bool) env('GOOGLE_PLACES_GEOCODING_RATELIMIT_ENABLED', true),
            'limit' => (int) env('GOOGLE_PLACES_GEOCODING_RATELIMIT', 600),
            'per' => env('GOOGLE_PLACES_GEOCODING_RATELIMIT_PER', 'minute'),
            'adaptive' => (bool) env('GOOGLE_PLACES_GEOCODING_RATELIMIT_ADAPTIVE', true),
            'max_wait' => env('GOOGLE_PLACES_GEOCODING_RATELIMIT_MAX_WAIT'),
            'jitter' => env('GOOGLE_PLACES_GEOCODING_RATELIMIT_JITTER'),
        ],

    ],

];
