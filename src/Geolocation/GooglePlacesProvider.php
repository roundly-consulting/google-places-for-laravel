<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Geolocation;

use RoundlyConsulting\Geolocation\DataTransferObjects\GeolocationQuery;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;
use RoundlyConsulting\Geolocation\Enum\GeolocationType;
use RoundlyConsulting\Geolocation\GeolocationProvider;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;

/**
 * Registers Google Places as a geolocation driver so a host running
 * geolocation-for-laravel can forward-/reverse-geocode through Google Places.
 *
 * Coordinate queries reverse-geocode via the Places client's geocode() call;
 * address queries forward-geocode via geocodeAddress(). Google Places has no IP
 * geolocation, so IP-only (or empty) queries resolve to null and fall through to
 * the next provider in the host's pipeline.
 *
 * @internal Wiring: registered by the service provider as the `google_places`
 *           geolocation driver. Use it through geolocation-for-laravel.
 */
final class GooglePlacesProvider implements GeolocationProvider
{
    public function __construct(private readonly PlacesClient $places) {}

    public function locate(GeolocationQuery $query): ?Location
    {
        if ($query->latitude !== null && $query->longitude !== null) {
            $result = $this->places->geocode($query->latitude, $query->longitude)->first();
        } elseif ($query->address !== null && $query->address !== '') {
            $result = $this->places->geocodeAddress($query->address)->first();
        } else {
            // IP-only or empty query — Google Places cannot resolve it.
            return null;
        }

        return $result instanceof ReverseGeocodingResult ? $this->toLocation($result) : null;
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
