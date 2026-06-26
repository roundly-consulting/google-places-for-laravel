<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\Rules\ValidPlaceId;

/**
 * @return list<string> The validation failures (empty when the value passes).
 */
function placeIdErrors(mixed $value): array
{
    $errors = [];

    (new ValidPlaceId)->validate('place', $value, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    return $errors;
}

it('passes a realistic place id', function () {
    expect(placeIdErrors('ChIJN1t_tDeuEmsRUsoyG83frY4'))->toBe([]);
});

it('fails an empty or malformed place id', function (mixed $value) {
    expect(placeIdErrors($value))->not->toBe([]);
})->with([
    'empty' => '',
    'too short' => 'abc',
    'spaces' => 'has spaces here',
    'symbols' => 'bad/id!',
    'not a string' => 12345,
    'array' => [[['x']]],
]);

it('reports a helpful message', function () {
    expect(placeIdErrors(''))->toContain('The :attribute must be a valid Google Place ID.');
});
