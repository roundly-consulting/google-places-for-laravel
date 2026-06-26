<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\AddressComponent;

it('holds address component details', function () {
    $component = new AddressComponent(
        'Very long name',
        'Short name',
        ['one', 'two'],
    );

    expect($component)
        ->longName->toBe('Very long name')
        ->shortName->toBe('Short name')
        ->types->toBe(['one', 'two']);
});

it('creates instance from google response', function () {
    $component = AddressComponent::fromGoogleResponse([
        'long_name' => 'Very long name',
        'short_name' => 'Short name',
        'types' => ['one', 'two'],
    ]);

    expect($component)
        ->longName->toBe('Very long name')
        ->shortName->toBe('Short name')
        ->types->toBe(['one', 'two']);
});
