<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RoundlyConsulting\GooglePlaces\Concerns\InteractsWithRateLimits;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ApiCheckResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceMatrix;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Roundtrip;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\SearchPage;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Support\PendingMatrix;
use RoundlyConsulting\GooglePlaces\Support\PendingPhoto;
use RoundlyConsulting\GooglePlaces\Support\PlacesSession;
use RoundlyConsulting\GooglePlaces\Support\Redactor;
use RoundlyConsulting\GooglePlaces\Support\SearchPaginator;
use SensitiveParameter;

final class Places implements PlacesClient
{
    use InteractsWithRateLimits;

    /**
     * The waypoint the Routes probe asks about: (0, 0) — any answer proves the API is on.
     */
    private const array NULL_ISLAND = ['waypoint' => ['location' => ['latLng' => ['latitude' => 0, 'longitude' => 0]]]];

    private float $startedAt = 0.0;

    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function session(?string $token = null): PlacesSession
    {
        return new PlacesSession($this, $token);
    }

    public function matrix(array $origins, array $destinations): PendingMatrix
    {
        return new PendingMatrix($this, $origins, $destinations);
    }

    public function photo(string $name, int $maxWidth = 1600, int $maxHeight = 1600): PendingPhoto
    {
        return new PendingPhoto($this, $name, $maxWidth, $maxHeight);
    }

    public function photoUri(string $name, int $maxWidth = 1600, int $maxHeight = 1600): string
    {
        $uri = $this->media('photoUri', $name, $maxWidth, $maxHeight, ['skipHttpRedirect' => 'true'])->json('photoUri');

        return is_string($uri) ? $uri : '';
    }

    public function photoContents(string $name, int $maxWidth = 1600, int $maxHeight = 1600): string
    {
        return $this->media('photoContents', $name, $maxWidth, $maxHeight)->body();
    }

    public function check(): array
    {
        $key = $this->key();

        return [
            $this->probe('Places API (New)', $key, fn (): Response => $this->probeClient('places')
                ->withHeaders(['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => 'places.id'])
                ->post('/places:searchText', ['textQuery' => 'test'])),
            $this->probe('Routes API', $key, fn (): Response => $this->probeClient('routes')
                ->withHeaders(['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => 'originIndex,destinationIndex,condition'])
                ->post('/distanceMatrix/v2:computeRouteMatrix', [
                    'origins' => [self::NULL_ISLAND],
                    'destinations' => [self::NULL_ISLAND],
                    'travelMode' => 'DRIVE',
                ])),
            $this->probe('Geocoding API', $key, fn (): Response => $this->probeClient('geocoding')
                ->get('/geocode/json', ['address' => 'Googleplex', 'key' => $key])),
        ];
    }

    public function photoUrl(string $name, int $maxWidth = 1600, int $maxHeight = 1600): string
    {
        $query = http_build_query([
            'maxWidthPx' => $maxWidth,
            'maxHeightPx' => $maxHeight,
            'key' => $this->key(),
        ]);

        return $this->host('places').'/'.ltrim($name, '/').'/media?'.$query;
    }

    public function details(DetailsQuery|string $query): ?Place
    {
        $query = is_string($query) ? new DetailsQuery($query) : $query;

        return $this->cached(
            'details',
            $this->detailsKey($query),
            function () use ($query): ?array {
                $mask = $query->fieldMask() ?? (string) config('google-places.field_masks.details');

                $response = $this->send('details', fn (): Response => $this->placesClient($mask)
                    ->get('/places/'.$query->place, $query->toRequest()));

                if ($response->successful()) {
                    $this->received('details', $response);

                    return (array) $response->json();
                }

                // A 404 is an ANSWER — "no such place" — and worth remembering. Every
                // other failure throws, so a transient outage is never cached.
                if ($response->status() === 404) {
                    $this->received('details', $response);

                    return null;
                }

                throw $this->failed('details', $response);
            },
            static fn (?array $payload): ?Place => $payload === null ? null : Place::fromResponse($payload),
        );
    }

    public function autocomplete(AutocompleteQuery|string $query): Collection
    {
        $query = is_string($query) ? new AutocompleteQuery($query) : $query;

        $response = $this->send('autocomplete', fn (): Response => $this->placesClient($this->mask('autocomplete'))
            ->post('/places:autocomplete', $query->toBody()));

        if (! $response->successful()) {
            throw $this->failed('autocomplete', $response);
        }

        $this->received('autocomplete', $response);

        return $response->collect('suggestions')
            ->filter(static fn (array $suggestion): bool => isset($suggestion['placePrediction']))
            ->map(static fn (array $suggestion): AutocompletePrediction => AutocompletePrediction::fromResponse((array) $suggestion['placePrediction']))
            ->values();
    }

    public function geocode(ReverseGeocodingQuery|Location|float $location, ?float $longitude = null): Collection
    {
        $query = match (true) {
            $location instanceof ReverseGeocodingQuery => $location,
            $location instanceof Location => new ReverseGeocodingQuery($location),
            default => new ReverseGeocodingQuery(new Location($location, (float) $longitude)),
        };

        return $this->cached(
            'geocode',
            $query->toRequest(),
            function () use ($query): array {
                $response = $this->send('geocode', fn (): Response => $this->geocodingClient()
                    ->get('/geocode/json', $query->toRequest()));

                if (in_array($response->json('status'), ['OK', 'ZERO_RESULTS'], true)) {
                    $this->received('geocode', $response);

                    return (array) $response->json();
                }

                throw $this->failed('geocode', $response);
            },
            static fn (?array $payload): Collection => self::geocodingResults($payload),
        );
    }

    public function geocodeAddress(GeocodingQuery|string $query): Collection
    {
        $query = is_string($query) ? new GeocodingQuery($query) : $query;

        return $this->cached(
            'geocodeAddress',
            $query->toRequest(),
            function () use ($query): array {
                $response = $this->send('geocodeAddress', fn (): Response => $this->geocodingClient()
                    ->get('/geocode/json', $query->toRequest()));

                if (in_array($response->json('status'), ['OK', 'ZERO_RESULTS'], true)) {
                    $this->received('geocodeAddress', $response);

                    return (array) $response->json();
                }

                throw $this->failed('geocodeAddress', $response);
            },
            static fn (?array $payload): Collection => self::geocodingResults($payload),
        );
    }

    public function textSearch(TextSearchQuery|string $query): Collection
    {
        $query = is_string($query) ? new TextSearchQuery($query) : $query;

        return $this->cached(
            'textSearch',
            $query->toBody(),
            fn (): array => $this->search('textSearch', '/places:searchText', $query->toBody()),
            static fn (?array $payload): Collection => self::places($payload),
        );
    }

    public function nearbySearch(NearbySearchQuery $query): Collection
    {
        return $this->cached(
            'nearbySearch',
            $query->toBody(),
            fn (): array => $this->search('nearbySearch', '/places:searchNearby', $query->toBody()),
            static fn (?array $payload): Collection => self::places($payload),
        );
    }

    public function textSearchPaginated(TextSearchQuery|string $query): SearchPaginator
    {
        $query = is_string($query) ? new TextSearchQuery($query) : $query;

        return new SearchPaginator(
            fn (?string $pageToken): SearchPage => $this->searchPage(
                'textSearch',
                '/places:searchText',
                ($pageToken === null ? $query : $query->withPageToken($pageToken))->toBody(),
            ),
            $this->maxPages(),
            'textSearch',
        );
    }

    public function nearbySearchPaginated(NearbySearchQuery $query): SearchPaginator
    {
        return new SearchPaginator(
            fn (?string $pageToken): SearchPage => $this->searchPage('nearbySearch', '/places:searchNearby', $query->toBody()),
            $this->maxPages(),
            'nearbySearch',
        );
    }

    public function computeMatrix(MatrixQuery $query): DistanceMatrix
    {
        return $this->cached(
            'computeMatrix',
            $query->toRoutesBody(),
            function () use ($query): array {
                $response = $this->send('computeMatrix', fn (): Response => $this->routesClient($this->mask('routes'))
                    ->post('/distanceMatrix/v2:computeRouteMatrix', $query->toRoutesBody()));

                if (! $response->successful()) {
                    throw $this->failed('computeMatrix', $response);
                }

                $this->received('computeMatrix', $response);

                $elements = $response->json();

                if (! is_array($elements)) {
                    throw $this->failed('computeMatrix', $response);
                }

                return $elements;
            },
            fn (?array $elements): DistanceMatrix => DistanceMatrix::fromRoutesElements(
                $elements ?? [],
                count($query->origins),
                count($query->destinations),
                $query->type,
            ),
        );
    }

    public function findPlace(string $text, ?Location $bias = null): ?Place
    {
        $query = (new TextSearchQuery($text))->take(1);

        if ($bias instanceof Location) {
            $query = $query->preferInArea((new LocationDefinition)->circle($bias));
        }

        return $this->textSearch($query)->first();
    }

    public function distance(DistanceQuery $query): Distance|Roundtrip
    {
        return $this->cached(
            'distance',
            $query->toRoutesBody(),
            function () use ($query): array {
                $response = $this->send('distance', fn (): Response => $this->routesClient($this->mask('routes'))
                    ->post('/distanceMatrix/v2:computeRouteMatrix', $query->toRoutesBody()));

                if (! $response->successful()) {
                    throw $this->failed('distance', $response);
                }

                $this->received('distance', $response);

                $elements = $response->json();

                if (! is_array($elements) || $elements === []) {
                    throw $this->failed('distance', $response);
                }

                return $elements;
            },
            function (?array $elements) use ($query): Distance|Roundtrip {
                // Decoding, not fetching: a cached payload has to raise the same
                // "no route" as a fresh one, or the answer would depend on whether
                // somebody asked before.
                if ($elements === null || $elements === []) {
                    throw PlacesException::routeNotFound(0, 0, null);
                }

                $elements = $this->sortRouteElements($elements);

                foreach ($elements as $index => $element) {
                    $condition = is_array($element) ? ($element['condition'] ?? null) : null;

                    if ($condition !== 'ROUTE_EXISTS') {
                        throw PlacesException::routeNotFound(0, $index, is_string($condition) ? $condition : null);
                    }
                }

                if (count($elements) > 1) {
                    return Roundtrip::fromRoutesElements($elements, $query->type);
                }

                return Distance::fromRoutesElement((array) $elements[0], $query->type);
            },
        );
    }

    /**
     * One request to a photo's `/media` endpoint, throttled and reported on the
     * `places` surface like every other Places call. The key travels as a header,
     * never in the URL.
     *
     * @param  array<string, string>  $extra
     */
    private function media(string $endpoint, string $name, int $maxWidth, int $maxHeight, array $extra = []): Response
    {
        $response = $this->send($endpoint, fn (): Response => $this->baseClient($this->host('places'))
            ->withHeaders(['X-Goog-Api-Key' => $this->key()])
            ->get('/'.ltrim($name, '/').'/media', array_merge([
                'maxWidthPx' => (string) $maxWidth,
                'maxHeightPx' => (string) $maxHeight,
            ], $extra)));

        if (! $response->successful()) {
            throw $this->failed($endpoint, $response);
        }

        $this->received($endpoint, $response);

        return $response;
    }

    /**
     * Run one health probe. A probe never throws: an unreachable or refusing API
     * is the answer it exists to report.
     *
     * @param  Closure(): Response  $request
     */
    private function probe(string $api, #[SensitiveParameter] string $key, Closure $request): ApiCheckResult
    {
        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            return new ApiCheckResult($api, false, Redactor::redact('Unreachable: '.$exception->getMessage(), $key));
        }

        // The legacy Geocoding API answers a refused key with HTTP 200 and a body `status`
        // of REQUEST_DENIED (recorded live), so an HTTP success alone is not "authorized".
        $status = PlacesException::statusFrom($response);

        if ($response->successful() && in_array($status, [null, 'OK', 'ZERO_RESULTS'], true)) {
            return new ApiCheckResult($api, true, 'Reachable and authorized.');
        }

        $message = PlacesException::fromResponse($response)->googleErrorMessage();

        $detail = trim(($response->status().' '.($status ?? '')).' '.($message ?? ''));

        return new ApiCheckResult($api, false, Redactor::redact($detail !== '' ? $detail : 'Request rejected.', $key));
    }

    /**
     * A probe client: no retries (a doctor reports the first failure rather than
     * papering over it), no throttling, no events.
     */
    private function probeClient(string $service): PendingRequest
    {
        return Http::baseUrl($this->host($service))
            ->timeout((int) config('google-places.http.timeout', 10))
            ->connectTimeout((int) config('google-places.http.connect_timeout', 5));
    }

    /**
     * One search, as the API's raw payload — {@see places()} turns it into places.
     *
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     */
    private function search(string $endpoint, string $uri, array $body): array
    {
        $response = $this->send($endpoint, fn (): Response => $this->placesClient($this->mask('search'))
            ->post($uri, $body));

        if (! $response->successful()) {
            throw $this->failed($endpoint, $response);
        }

        $this->received($endpoint, $response);

        return (array) $response->json();
    }

    /**
     * The `places` of a search payload, as `Place` objects.
     *
     * @param  array<mixed>|null  $payload
     * @return Collection<int, Place>
     */
    private static function places(?array $payload): Collection
    {
        $places = is_array($payload['places'] ?? null) ? $payload['places'] : [];

        return Collection::make($places)
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->map(static fn (array $place): Place => Place::fromResponse($place))
            ->values();
    }

    /**
     * The `results` of a geocoding payload, as reverse-geocoding results.
     *
     * @param  array<mixed>|null  $payload
     * @return Collection<int, ReverseGeocodingResult>
     */
    private static function geocodingResults(?array $payload): Collection
    {
        $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];

        return Collection::make($results)
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->map(static fn (array $item): ReverseGeocodingResult => ReverseGeocodingResult::fromResponse($item))
            ->values();
    }

    /**
     * Fetch one page of a search and surface its `nextPageToken`.
     *
     * @param  array<string, mixed>  $body
     */
    private function searchPage(string $endpoint, string $uri, array $body): SearchPage
    {
        $response = $this->send($endpoint, fn (): Response => $this->placesClient($this->mask('search').',nextPageToken')
            ->post($uri, $body));

        if (! $response->successful()) {
            throw $this->failed($endpoint, $response);
        }

        $this->received($endpoint, $response);

        $places = array_values($response->collect('places')
            ->map(static fn (array $place): Place => Place::fromResponse($place))
            ->all());

        $token = $response->json('nextPageToken');

        return new SearchPage($places, is_string($token) ? $token : null);
    }

    private function maxPages(): int
    {
        return max(1, (int) config('google-places.pagination.max_pages', 5));
    }

    /**
     * @param  array<int, mixed>  $elements
     * @return array<int, mixed>
     */
    private function sortRouteElements(array $elements): array
    {
        usort($elements, static function (mixed $a, mixed $b): int {
            $a = (array) $a;
            $b = (array) $b;

            return [(int) ($a['originIndex'] ?? 0), (int) ($a['destinationIndex'] ?? 0)]
                <=> [(int) ($b['originIndex'] ?? 0), (int) ($b['destinationIndex'] ?? 0)];
        });

        return $elements;
    }

    private function placesClient(string $fieldMask): PendingRequest
    {
        return $this->baseClient($this->host('places'))->withHeaders([
            'X-Goog-Api-Key' => $this->key(),
            'X-Goog-FieldMask' => $fieldMask,
        ]);
    }

    private function routesClient(string $fieldMask): PendingRequest
    {
        return $this->baseClient($this->host('routes'))->withHeaders([
            'X-Goog-Api-Key' => $this->key(),
            'X-Goog-FieldMask' => $fieldMask,
        ]);
    }

    private function geocodingClient(): PendingRequest
    {
        return $this->baseClient($this->host('geocoding'))->withQueryParameters([
            'key' => $this->key(),
        ]);
    }

    private function baseClient(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->timeout((int) config('google-places.http.timeout', 10))
            ->connectTimeout((int) config('google-places.http.connect_timeout', 5))
            ->retry(
                (int) config('google-places.http.retries', 2) + 1,
                (int) config('google-places.http.retry_delay', 200),
                static fn (mixed $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            );
    }

    /**
     * Run a request, converting a connection failure into a PlacesException.
     *
     * @param  Closure(): Response  $request
     */
    private function send(string $endpoint, Closure $request): Response
    {
        $this->startedAt = microtime(true);

        try {
            return $this->throttled($this->surfaceFor($endpoint), $request);
        } catch (ConnectionException $exception) {
            throw $this->connectionFailed($endpoint, $exception);
        }
    }

    /**
     * Map an endpoint to its Google API surface so each is rate-limited on its
     * own bucket (mirrors the `hosts` grouping).
     */
    private function surfaceFor(string $endpoint): string
    {
        return match ($endpoint) {
            'distance', 'computeMatrix' => 'routes',
            'geocode', 'geocodeAddress' => 'geocoding',
            default => 'places',
        };
    }

    private function received(string $endpoint, Response $response): void
    {
        $this->events->dispatch(new PlacesResponseReceived(
            endpoint: $endpoint,
            httpStatus: $response->status(),
            googleStatus: PlacesException::statusFrom($response),
            durationMs: $this->durationMs(),
        ));
    }

    private function failed(string $endpoint, Response $response): PlacesException
    {
        $exception = PlacesException::fromResponse($response);

        $this->events->dispatch(new PlacesRequestFailed(
            endpoint: $endpoint,
            httpStatus: $response->status(),
            googleStatus: $exception->googleStatus,
            message: $exception->googleErrorMessage ?? '',
        ));

        return $exception;
    }

    private function connectionFailed(string $endpoint, ConnectionException $exception): PlacesException
    {
        $placesException = PlacesException::connectionFailed($exception);

        $this->events->dispatch(new PlacesRequestFailed(
            endpoint: $endpoint,
            httpStatus: 0,
            googleStatus: null,
            message: $placesException->getMessage(),
        ));

        return $placesException;
    }

    private function durationMs(): int
    {
        return (int) round((microtime(true) - $this->startedAt) * 1000);
    }

    /**
     * Memoize one idempotent lookup — as the API's own PAYLOAD, never as the object
     * built from it.
     *
     * A cache store is allowed to refuse to unserialize classes, and Laravel's is
     * configured that way by default (`cache.serializable_classes`, which guards
     * against gadget chains if `APP_KEY` leaks). Under that setting an object put
     * into the cache comes back a `__PHP_Incomplete_Class`, so a method typed
     * `?Place` returns something that fails every type check the moment it is
     * touched — on the request that READS the entry, never on the one that wrote
     * it. Caching the decoded JSON and rebuilding through the same factory the
     * live path uses removes the possibility rather than documenting it.
     *
     * The payload is wrapped, because `null` is a real answer here: a 404 from
     * `details` means "no such place", and `remember()` cannot tell that from a
     * miss — so it re-billed Google for every repeat of a question already
     * answered.
     *
     * @template TValue
     *
     * @param  array<string, mixed>  $params  what makes this lookup unique
     * @param  Closure(): (array<mixed>|null)  $fetch  the raw, JSON-shaped payload
     * @param  Closure(array<mixed>|null): TValue  $decode  payload → the returned type
     * @return TValue
     */
    private function cached(string $endpoint, array $params, Closure $fetch, Closure $decode): mixed
    {
        if (! (bool) config('google-places.cache.enabled', false)) {
            return $decode($fetch());
        }

        $key = 'google-places:'.$endpoint.':'.sha1((string) json_encode($params));
        $store = Cache::store($this->cacheStore());
        $cached = $store->get($key);

        // Only this service's own wrapper counts as a hit. Anything else — a payload
        // from an older version of this package, or an object a previous one stored —
        // is a MISS, so an unreadable entry costs one lookup instead of throwing.
        if (is_array($cached) && array_key_exists('payload', $cached)) {
            $payload = $cached['payload'];

            if ($payload === null || is_array($payload)) {
                return $decode($payload);
            }
        }

        $payload = $fetch();

        $store->put($key, ['payload' => $payload], (int) config('google-places.cache.ttl', 86400));

        return $decode($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailsKey(DetailsQuery $query): array
    {
        return [
            'place' => $query->place,
            'fields' => $query->fieldMask() ?? (string) config('google-places.field_masks.details'),
            'request' => $query->toRequest(),
        ];
    }

    private function cacheStore(): ?string
    {
        $store = config('google-places.cache.store');

        return is_string($store) ? $store : null;
    }

    /**
     * Literal keys rather than `config("google-places.field_masks.{$endpoint}")`: an
     * interpolated key cannot be checked against the shipped config file, and an
     * unverifiable read is how a package ends up reading a key it never ships (shops #18)
     * or shipping one nothing reads (media #27's size cap that never applied). The match
     * is exhaustive over the three masks routed through here, so an unknown endpoint throws
     * instead of sending Google an empty `X-Goog-FieldMask` — which the API rejects anyway,
     * one network round-trip later. (`field_masks.details` is read directly at its two call
     * sites, which is why it is not a branch here.)
     */
    private function mask(string $endpoint): string
    {
        return (string) match ($endpoint) {
            'autocomplete' => config('google-places.field_masks.autocomplete'),
            'search' => config('google-places.field_masks.search'),
            'routes' => config('google-places.field_masks.routes'),
            default => throw new InvalidArgumentException("Unknown field mask endpoint [{$endpoint}]."),
        };
    }

    /**
     * Literal keys, for the same reason as {@see self::mask()}. The three hosts mirror
     * Google's three products; an unknown service used to fall through to `''`, which
     * silently sent the request to the app's own origin.
     */
    private function host(string $service): string
    {
        $host = match ($service) {
            'places' => config('google-places.hosts.places'),
            'routes' => config('google-places.hosts.routes'),
            'geocoding' => config('google-places.hosts.geocoding'),
            default => throw new InvalidArgumentException("Unknown Google service [{$service}]."),
        };

        return is_string($host) ? rtrim($host, '/') : '';
    }

    private function key(): string
    {
        $key = config('google-places.key');

        if (! is_string($key) || $key === '') {
            throw PlacesException::missingApiKey();
        }

        return $key;
    }
}
