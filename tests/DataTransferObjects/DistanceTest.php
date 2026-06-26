<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;

it('holds values', function () {
    $distance = new Distance(
        humanReadableDistance: '10km',
        distanceInMeters: 10000,
        humanReadableDuration: '10min',
        durationInSeconds: 600,
        type: 'walking',
    );

    expect($distance)
        ->humanReadableDistance->toBe('10km')
        ->distanceInMeters->toBe(10000)
        ->humanReadableDuration->toBe('10min')
        ->durationInSeconds->toBe(600)
        ->type->toBe('walking');
});

it('checks type by methods', function () {
    $distance = new Distance(
        humanReadableDistance: '10km',
        distanceInMeters: 10000,
        humanReadableDuration: '10min',
        durationInSeconds: 600,
        type: 'walking',
    );

    expect($distance)
        ->isDriving()->toBeFalse()
        ->isWalking()->toBeTrue();

    $driving = new Distance(
        humanReadableDistance: '10km',
        distanceInMeters: 10000,
        humanReadableDuration: '10min',
        durationInSeconds: 600,
        type: 'driving',
    );

    expect($driving)
        ->isDriving()->toBeTrue()
        ->isWalking()->toBeFalse();
});

it('creates instance from google response item', function () {
    $distance = Distance::fromGoogleResponse([
        'distance' => ['text' => '10km', 'value' => 10000],
        'duration' => ['text' => '10min', 'value' => 600],
    ]);

    expect($distance)
        ->humanReadableDistance->toBe('10km')
        ->distanceInMeters->toBe(10000)
        ->humanReadableDuration->toBe('10m')
        ->durationInSeconds->toBe(600)
        ->type->toBe('driving');
});

it('creates instance from google response item with walking type', function () {
    $distance = Distance::fromGoogleResponse([
        'distance' => ['text' => '10km', 'value' => 10000],
        'duration' => ['text' => '10min', 'value' => 600],
    ], 'walking');

    expect($distance)
        ->humanReadableDistance->toBe('10km')
        ->distanceInMeters->toBe(10000)
        ->humanReadableDuration->toBe('10m')
        ->durationInSeconds->toBe(600)
        ->type->toBe('walking');
});
