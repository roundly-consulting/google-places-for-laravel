<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function places(): PlacesClient
{
    return app(PlacesClient::class);
}

/**
 * A Places API (New) place resource used across the HTTP tests.
 *
 * @return array<string, mixed>
 */
function placeResponse(): array
{
    return [
        'id' => 'place-1',
        'displayName' => ['text' => 'Somewhere'],
        'formattedAddress' => '123 Main St',
        'types' => ['home'],
        'location' => ['latitude' => 1, 'longitude' => 2],
        'viewport' => [
            'high' => ['latitude' => 3, 'longitude' => 4],
            'low' => ['latitude' => 5, 'longitude' => 6],
        ],
        'regularOpeningHours' => [
            'openNow' => true,
            'periods' => [
                [
                    'open' => ['day' => 1, 'hour' => 10, 'minute' => 0],
                    'close' => ['day' => 1, 'hour' => 17, 'minute' => 30],
                ],
            ],
        ],
        'photos' => [
            ['name' => 'places/place-1/photos/abc'],
        ],
    ];
}
