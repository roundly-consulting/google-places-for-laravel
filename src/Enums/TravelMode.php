<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Enums;

use RoundlyConsulting\Enums\Helpers;

enum TravelMode: string
{
    use Helpers;

    case Driving = 'driving';
    case Walking = 'walking';
    case Bicycling = 'bicycling';
    case Transit = 'transit';

    /**
     * The value the Routes API expects for `travelMode`.
     */
    public function routesValue(): string
    {
        return match ($this) {
            self::Driving => 'DRIVE',
            self::Walking => 'WALK',
            self::Bicycling => 'BICYCLE',
            self::Transit => 'TRANSIT',
        };
    }
}
