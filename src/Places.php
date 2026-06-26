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
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Roundtrip;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

final class Places implements PlacesClient
{
    private float $startedAt = 0.0;

    public function __construct(
        private readonly Dispatcher $events,
    ) {}

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

        return $this->cached('details', $this->detailsKey($query), function () use ($query): ?Place {
            $mask = $query->fieldMask() ?? (string) config('google-places.field_masks.details');

            $response = $this->send('details', fn (): Response => $this->placesClient($mask)
                ->get('/places/'.$query->place, $query->toRequest()));

            if ($response->successful()) {
                $this->received('details', $response);

                return Place::fromResponse((array) $response->json());
            }

            if ($response->status() === 404) {
                $this->received('details', $response);

                return null;
            }

            throw $this->failed('details', $response);
        });
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

        return $this->cached('geocode', $query->toRequest(), function () use ($query): Collection {
            $response = $this->send('geocode', fn (): Response => $this->geocodingClient()
                ->get('/geocode/json', $query->toRequest()));

            if (in_array($response->json('status'), ['OK', 'ZERO_RESULTS'], true)) {
                $this->received('geocode', $response);

                return $response->collect('results')
                    ->map(static fn (array $item): ReverseGeocodingResult => ReverseGeocodingResult::fromResponse($item));
            }

            throw $this->failed('geocode', $response);
        });
    }

    public function textSearch(TextSearchQuery|string $query): Collection
    {
        $query = is_string($query) ? new TextSearchQuery($query) : $query;

        return $this->cached('textSearch', $query->toBody(), fn (): Collection => $this->search('textSearch', '/places:searchText', $query->toBody()));
    }

    public function nearbySearch(NearbySearchQuery $query): Collection
    {
        return $this->cached('nearbySearch', $query->toBody(), fn (): Collection => $this->search('nearbySearch', '/places:searchNearby', $query->toBody()));
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
        return $this->cached('distance', $query->toRoutesBody(), function () use ($query): Distance|Roundtrip {
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
        });
    }

    /**
     * @param  array<string, mixed>  $body
     * @return Collection<int, Place>
     */
    private function search(string $endpoint, string $uri, array $body): Collection
    {
        $response = $this->send($endpoint, fn (): Response => $this->placesClient($this->mask('search'))
            ->post($uri, $body));

        if (! $response->successful()) {
            throw $this->failed($endpoint, $response);
        }

        $this->received($endpoint, $response);

        return $response->collect('places')
            ->map(static fn (array $place): Place => Place::fromResponse($place))
            ->values();
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
            return $request();
        } catch (ConnectionException $exception) {
            throw $this->connectionFailed($endpoint, $exception);
        }
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
     * @template TValue
     *
     * @param  array<string, mixed>  $params
     * @param  Closure(): TValue  $resolve
     * @return TValue
     */
    private function cached(string $endpoint, array $params, Closure $resolve): mixed
    {
        if (! (bool) config('google-places.cache.enabled', false)) {
            return $resolve();
        }

        $key = 'google-places:'.$endpoint.':'.sha1((string) json_encode($params));

        return Cache::store($this->cacheStore())->remember(
            $key,
            (int) config('google-places.cache.ttl', 86400),
            $resolve,
        );
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

    private function mask(string $endpoint): string
    {
        return (string) config("google-places.field_masks.{$endpoint}");
    }

    private function host(string $service): string
    {
        $host = config("google-places.hosts.{$service}");

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
