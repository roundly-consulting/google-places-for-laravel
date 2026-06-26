<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;

it('adds a circular bias to a text search query', function () {
    $body = (new TextSearchQuery('pizza'))->nearby(48.1, 17.1, 2000)->toBody();

    expect($body['locationBias']['circle'])->toBe([
        'center' => ['latitude' => 48.1, 'longitude' => 17.1],
        'radius' => 2000,
    ]);
});

it('adds a rectangular restriction to a text search query', function () {
    $body = (new TextSearchQuery('pizza'))
        ->withinBounds(new Location(1, 2), new Location(3, 4))
        ->toBody();

    expect($body['locationRestriction']['rectangle'])->toBe([
        'low' => ['latitude' => 1.0, 'longitude' => 2.0],
        'high' => ['latitude' => 3.0, 'longitude' => 4.0],
    ]);
});

it('adds a circular bias to an autocomplete query', function () {
    $body = (new AutocompleteQuery('cof'))->nearby(48.1, 17.1)->toBody();

    expect($body['locationBias']['circle']['radius'])->toBe(5000);
});

it('adds a rectangular restriction to an autocomplete query', function () {
    $body = (new AutocompleteQuery('cof'))
        ->withinBounds(new Location(1, 2), new Location(3, 4))
        ->toBody();

    expect($body['locationRestriction']['rectangle']['high'])->toBe(['latitude' => 3.0, 'longitude' => 4.0]);
});

it('keeps the queries immutable when biasing', function () {
    $base = new TextSearchQuery('pizza');

    expect($base->nearby(1, 2))->not->toBe($base)
        ->and($base->toBody())->not->toHaveKey('locationBias');
});
