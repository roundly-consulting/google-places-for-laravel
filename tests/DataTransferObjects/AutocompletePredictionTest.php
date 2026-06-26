<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;

it('holds autocomplete prediction', function () {
    $prediction = new AutocompletePrediction(
        'Something',
        '123',
        '456',
        ['one', 'two'],
        ['raw' => true],
    );

    expect($prediction)
        ->description->toBe('Something')
        ->placeId->toBe('123')
        ->reference->toBe('456')
        ->types->toBe(['one', 'two'])
        ->raw->toBe(['raw' => true]);
});

it('creates instance from google response', function () {
    $prediction = AutocompletePrediction::fromGoogleResponse($raw = [
        'description' => 'Something',
        'place_id' => '123',
        'reference' => '456',
        'types' => ['one', 'two'],
    ]);

    expect($prediction)
        ->description->toBe('Something')
        ->placeId->toBe('123')
        ->reference->toBe('456')
        ->types->toBe(['one', 'two'])
        ->raw->toBe($raw);
});
