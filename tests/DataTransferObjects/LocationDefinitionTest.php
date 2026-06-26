<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;

it('holds definition in value', function () {
    $definition = new LocationDefinition('ipbias');

    expect($definition->value)->toBe('ipbias');
});

it('sets value to circular definition', function () {
    $definition = new LocationDefinition;

    $definition->circular(new Location(1, 2));

    expect($definition->value)->toBe('circle:5000@1,2');
});

it('sets value to rectangular definition', function () {
    $definition = new LocationDefinition;

    $definition->rectangular(1, 2, 3, 4);

    expect($definition->value)->toBe('rectangle:1,2|3,4');
});
