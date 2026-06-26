<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;

it('holds values', function () {
    $q = new DistanceQuery(
        from: new Location(1, 2),
        to: new Location(2, 3),
        type: 'walking',
    );

    expect($q)
        ->from->toRequest()->toBe('1,2')
        ->to->toRequest()->toBe('2,3')
        ->type->toBe('walking');
});

it('changes type by methods', function () {
    $q = new DistanceQuery(
        from: new Location(1, 2),
        to: new Location(2, 3),
        type: 'walking',
    );

    expect($q)->type->toBe('walking');

    $q->driving();
    expect($q)->type->toBe('driving');

    $q->walking();
    expect($q)->type->toBe('walking');
});

it('returns values to request', function () {
    $q = new DistanceQuery(
        from: new Location(1, 2),
        to: new Location(2, 3),
        type: 'walking',
    );

    expect($q->toRequest())->toBe([
        'destinations' => '2,3',
        'origins' => '1,2',
        'mode' => 'walking',
        'language' => 'en',
    ]);

    Carbon::setTestNow('2023-11-08 10:00:00');

    $q->departureAt = now();

    expect($q->toRequest())->toBe([
        'destinations' => '2,3',
        'origins' => '1,2',
        'mode' => 'walking',
        'language' => 'en',
        'departure_time' => 1699437600,
    ]);
});
