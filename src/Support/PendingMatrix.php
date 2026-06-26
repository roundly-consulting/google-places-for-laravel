<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Support;

use Illuminate\Support\Carbon;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceMatrix;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixQuery;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

/**
 * Fluent builder for a many-origins × many-destinations distance matrix. The
 * travel-mode methods (driving(), walking(), …) execute the request.
 */
final class PendingMatrix
{
    private ?Carbon $departureAt = null;

    /**
     * @param  list<Location>  $origins
     * @param  list<Location>  $destinations
     */
    public function __construct(
        private readonly PlacesClient $client,
        private readonly array $origins,
        private readonly array $destinations,
    ) {}

    public function departingAt(?Carbon $departureAt): self
    {
        $this->departureAt = $departureAt;

        return $this;
    }

    /**
     * @throws PlacesException
     */
    public function driving(): DistanceMatrix
    {
        return $this->travellingBy(TravelMode::Driving);
    }

    /**
     * @throws PlacesException
     */
    public function walking(): DistanceMatrix
    {
        return $this->travellingBy(TravelMode::Walking);
    }

    /**
     * @throws PlacesException
     */
    public function bicycling(): DistanceMatrix
    {
        return $this->travellingBy(TravelMode::Bicycling);
    }

    /**
     * @throws PlacesException
     */
    public function transit(): DistanceMatrix
    {
        return $this->travellingBy(TravelMode::Transit);
    }

    /**
     * @throws PlacesException
     */
    public function travellingBy(TravelMode $mode): DistanceMatrix
    {
        return $this->client->computeMatrix(new MatrixQuery(
            origins: $this->origins,
            destinations: $this->destinations,
            type: $mode,
            departureAt: $this->departureAt,
        ));
    }
}
