<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;

it('holds geolocation and returns it as string to request', function () {
    expect(new Location(12.34, 56.78))
        ->latitude->toBe(12.34)
        ->longitude->toBe(56.78)
        ->toRequest()->toBe('12.34,56.78');
});
