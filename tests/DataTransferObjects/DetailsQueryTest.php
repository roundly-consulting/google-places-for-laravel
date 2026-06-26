<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;

it('holds values', function () {
    $q = new DetailsQuery(
        'Somewhere',
        ['test'],
        'sk',
        'ok',
    );

    expect($q)
        ->place->toBe('Somewhere')
        ->fields->toBe(['test'])
        ->language->toBe('sk')
        ->sessionToken->toBe('ok');
});

it('returns array to request', function () {
    $q = new DetailsQuery(
        'Somewhere',
        ['test'],
        'sk',
        'ok',
    );

    expect($q->toRequest())->toBe([
        'place_id' => 'Somewhere',
        'fields' => 'test',
        'language' => 'sk',
        'sessiontoken' => 'ok',
    ]);
});
