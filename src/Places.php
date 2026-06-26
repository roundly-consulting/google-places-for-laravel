<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Roundtrip;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

final class Places
{
    public function photoUrl(string $photo, int $maxWidth = 1600, int $maxHeight = 1600): string
    {
        $query = http_build_query([
            'photo_reference' => $photo,
            'maxwidth' => $maxWidth,
            'maxheight' => $maxHeight,
            'key' => $this->key(),
        ]);

        return $this->baseUrl().'/place/photo?'.$query;
    }

    /**
     * @throws PlacesException
     */
    public function details(DetailsQuery $query): ?Place
    {
        $response = $this->client()
            ->get('/place/details/json', $query->toRequest());

        if (in_array($response->json('status'), ['NOT_FOUND', 'ZERO_RESULTS'], true)) {
            return null;
        }

        if ($response->json('status') === 'OK') {
            return Place::fromGoogleResponse($response->json());
        }

        throw PlacesException::fromResponse($response);
    }

    /**
     * @return Collection<int, AutocompletePrediction>
     *
     * @throws PlacesException
     */
    public function autocomplete(AutocompleteQuery $query): Collection
    {
        $response = $this->client()
            ->get('/place/autocomplete/json', $query->toRequest());

        if (in_array($response->json('status'), ['OK', 'ZERO_RESULTS'], true)) {
            return $response->collect('predictions')
                ->map(static fn (array $item): AutocompletePrediction => AutocompletePrediction::fromGoogleResponse($item));
        }

        throw PlacesException::fromResponse($response);
    }

    /**
     * @return Collection<int, ReverseGeocodingResult>
     *
     * @throws PlacesException
     */
    public function geocode(ReverseGeocodingQuery $query): Collection
    {
        $response = $this->client()
            ->get('/geocode/json', $query->toRequest());

        if (in_array($response->json('status'), ['OK', 'ZERO_RESULTS'], true)) {
            return $response->collect('results')
                ->map(static fn (array $item): ReverseGeocodingResult => ReverseGeocodingResult::fromGoogleResponse($item));
        }

        throw PlacesException::fromResponse($response);
    }

    /**
     * @throws PlacesException
     */
    public function distance(DistanceQuery $query): Distance|Roundtrip
    {
        $response = $this->client()
            ->get('/distancematrix/json', $query->toRequest());

        if ($response->successful() && $response->json('status') === 'OK') {
            $elements = $response->json('rows.0.elements');

            if (! is_array($elements)) {
                throw PlacesException::fromResponse($response);
            }

            if (count($elements) > 1) {
                return Roundtrip::fromGoogleResponse(
                    distances: $elements,
                    type: $query->type,
                );
            }

            return Distance::fromGoogleResponse(
                item: $elements[0],
                type: $query->type,
            );
        }

        throw PlacesException::fromResponse($response);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withQueryParameters([
                'key' => $this->key(),
            ]);
    }

    private function key(): string
    {
        return (string) config('google-places.key');
    }

    private function baseUrl(): string
    {
        return (string) config('google-places.base_url', 'https://maps.googleapis.com/maps/api');
    }
}
