<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\OpeningHourPeriod;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\OpeningHours;

it('holds values', function () {
    $oh = new OpeningHours(true, [1]);

    expect($oh)
        ->isOpen->toBeTrue()
        ->periods->toBe([1]);
});

it('creates instance from google response and maps periods to dto', function () {
    $oh = OpeningHours::fromGoogleResponse([
        'open_now' => true,
        'periods' => [
            [
                'open' => ['day' => 1, 'time' => '10:00'],
                'close' => ['day' => 1, 'time' => '17:00'],
            ],
        ],
    ]);

    expect($oh)
        ->isOpen->toBeTrue()
        ->periods->toHaveCount(1);

    expect($oh->periods[0])
        ->toBeInstanceOf(OpeningHourPeriod::class)
        ->day->toBe(1)
        ->from->toBe('10:00')
        ->to->toBe('17:00');
});
