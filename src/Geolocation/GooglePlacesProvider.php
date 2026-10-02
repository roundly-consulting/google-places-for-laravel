<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Geolocation;

use RoundlyConsulting\Geolocation\DataTransferObjects\GeolocationQuery;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;
use RoundlyConsulting\Geolocation\Enum\GeolocationType;
use RoundlyConsulting\Geolocation\Exceptions\ProviderUnavailableException;
use RoundlyConsulting\Geolocation\GeolocationProvider;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

/**
 * Registers Google Places as a geolocation driver so a host running
 * geolocation-for-laravel can forward-/reverse-geocode through Google Places.
 *
 * Coordinate queries reverse-geocode via the Places client's geocode() call;
 * address queries forward-geocode via geocodeAddress(). Google Places has no IP
 * geolocation, so IP-only (or empty) queries resolve to null and fall through to
 * the next provider in the host's pipeline.
 *
 * Like geolocation's own providers, it never aborts the host's pipeline: an
 * unreachable Google becomes a ProviderUnavailableException (which the manager records
 * and skips), and every other Places failure — a missing key, REQUEST_DENIED,
 * OVER_QUERY_LIMIT, a client-side rate-limit fail-fast — is a miss (null).
 *
 * @internal Wiring: registered by the service provider as the `google_places`
 *           geolocation driver. Use it through geolocation-for-laravel.
 */
final class GooglePlacesProvider implements GeolocationProvider
{
    public function __construct(private readonly PlacesClient $places) {}

    public function locate(GeolocationQuery $query): ?Location
    {
        try {
            $result = $this->lookup($query);
        } catch (PlacesException $exception) {
            if ($exception->isUnreachable()) {
                // The message is already redacted; the transport exception is not chained.
                throw ProviderUnavailableException::for('google_places', $exception);
            }

            return null;
        }

        return $result instanceof ReverseGeocodingResult ? $this->toLocation($result) : null;
    }

    private function lookup(GeolocationQuery $query): ?ReverseGeocodingResult
    {
        if ($query->latitude !== null && $query->longitude !== null) {
            return $this->places->geocode($query->latitude, $query->longitude)->first();
        }

        if ($query->address !== null && $query->address !== '') {
            return $this->places->geocodeAddress($query->address)->first();
        }

        // IP-only or empty query — Google Places cannot resolve it.
        return null;
    }

    /**
     * Map a Google Places geocoding result onto geolocation's Location DTO,
     * field-for-field identical to geolocation's own GoogleProvider.
     */
    private function toLocation(ReverseGeocodingResult $result): Location
    {
        $components = $result->components();

        $streetNumber = $components->streetNumber();
        $route = $components->street();
        $street = trim(($streetNumber !== null ? $streetNumber.' ' : '').($route ?? ''));

        return new Location(
            humanReadable: $result->address,
            street: $street,
            city: $components->city() ?? '',
            countryIsoCode: $components->countryCode() ?? '',
            latitude: $result->geometry->location->latitude,
            longitude: $result->geometry->location->longitude,
            type: GeolocationType::Geolocation,
            region: $components->state() ?? '',
            postalCode: $components->postalCode() ?? '',
            timezone: '',
        );
    }
}
