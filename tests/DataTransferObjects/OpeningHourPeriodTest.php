<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\OpeningHourPeriod;

it('holds values', function () {
    $op = new OpeningHourPeriod(1, '10:00', '17:00');

    expect($op)
        ->day->toBe(1)
        ->from->toBe('10:00')
        ->to->toBe('17:00');
});

it('creates instance from google response', function () {
    $op = OpeningHourPeriod::fromGoogleResponse([
        'open' => ['day' => 1, 'time' => '10:00'],
        'close' => ['day' => 1, 'time' => '17:00'],
    ]);

    expect($op)
        ->toBeInstanceOf(OpeningHourPeriod::class)
        ->day->toBe(1)
        ->from->toBe('10:00')
        ->to->toBe('17:00');
});
