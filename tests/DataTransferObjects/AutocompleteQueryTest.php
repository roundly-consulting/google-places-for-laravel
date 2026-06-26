<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;

it('holds values', function () {
    $q = new AutocompleteQuery(
        'Something',
        ['ok'],
        'sk',
        new LocationDefinition('test'),
        new LocationDefinition('test2'),
        20,
        new Location(1, 2),
        'gb',
        'one',
        true,
        ['home'],
    );

    expect($q)
        ->query->toBe('Something')
        ->components->toBe(['ok'])
        ->language->toBe('sk')
        ->locationBias->value->toBe('test')
        ->locationRestriction->value->toBe('test2')
        ->offset->toBe(20)
        ->origin->toRequest()->toBe('1,2')
        ->region->toBe('gb')
        ->sessionToken->toBe('one')
        ->strictBounds->toBeTrue()
        ->types->toBe(['home']);
});

it('sets values using setters', function () {
    $q = new AutocompleteQuery;

    $q->query('Something')
        ->withComponents(['ok'])
        ->inLanguage('sk')
        ->preferInArea(new LocationDefinition('test'))
        ->restrictLocationBy(new LocationDefinition('test2'))
        ->usingOffset(20)
        ->inRegion('gb')
        ->fromOrigin(new Location(1, 2))
        ->usingSessionToken('one')
        ->withStrictBoundary()
        ->ofType('home');

    expect($q)
        ->query->toBe('Something')
        ->components->toBe(['ok'])
        ->language->toBe('sk')
        ->locationBias->value->toBe('test')
        ->locationRestriction->value->toBe('test2')
        ->offset->toBe(20)
        ->origin->toRequest()->toBe('1,2')
        ->region->toBe('gb')
        ->sessionToken->toBe('one')
        ->strictBounds->toBeTrue()
        ->types->toBe(['home']);

    $q->preferInAreaByIpAddress()
        ->withoutLocationRestriction();

    expect($q->locationBias->value)->toBe('ipbias')
        ->and($q->locationRestriction->value)->toBeNull();
});

it('returns array to request', function () {
    $q = new AutocompleteQuery('Something');

    $q->withComponents(['ok'])
        ->inLanguage('sk')
        ->preferInArea(new LocationDefinition('test'))
        ->restrictLocationBy(new LocationDefinition('test2'))
        ->usingOffset(20)
        ->inRegion('gb')
        ->fromOrigin(new Location(1, 2))
        ->usingSessionToken('one')
        ->withStrictBoundary()
        ->ofType('home');

    expect($q->toRequest())->toBe([
        'input' => 'Something',
        'language' => 'sk',
        'components' => 'ok',
        'types' => 'home',
        'locationbias' => 'test',
        'locationrestriction' => 'test2',
        'offset' => 20,
        'origin' => '1,2',
        'region' => 'gb',
        'sessiontoken' => 'one',
        'strictbounds' => true,
    ]);
});
