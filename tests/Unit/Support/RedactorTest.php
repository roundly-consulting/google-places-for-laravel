<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use RoundlyConsulting\GooglePlaces\Support\Redactor;

it('masks the configured key wherever it appears, raw or url-encoded', function () {
    config()->set('google-places.key', 'AIza/Secret+Key=99');

    $text = Redactor::redact('raw AIza/Secret+Key=99, encoded '.rawurlencode('AIza/Secret+Key=99').' and '.urlencode('AIza/Secret+Key=99'));

    expect($text)->not->toContain('Secret')
        ->and(substr_count($text, '=99'))->toBe(3);
});

it('treats a blank configured key as not set, leaving the text alone', function (string $blank) {
    // A whitespace-only key used to be "set": every run of spaces in a message was masked.
    config()->set('google-places.key', $blank);

    expect(Redactor::redact('a  b   c'))->toBe('a  b   c');
})->with(['empty env' => [''], 'whitespace' => ['  ']]);

it('masks every credential query parameter whatever its value', function () {
    expect(Redactor::redact('GET https://x.test/a?key=OtherKey12345&address=Main+St&token=tok_abcdefgh9&signature=s', key: 'unused-key'))
        ->toBe('GET https://x.test/a?key=*********2345&address=Main+St&token=*********fgh9&signature=*');
});

it('keeps the last four characters only of a long enough secret', function (string $secret, string $mask) {
    expect(Redactor::mask($secret))->toBe($mask);
})->with([
    'long' => ['AIzaSECRETKEY1234', '*************1234'],
    'nine' => ['123456789', '*****6789'],
    'eight' => ['12345678', '********'],
    'empty' => ['', ''],
]);

it('is idempotent', function () {
    $once = Redactor::redact('for https://maps.test/geocode/json?key=GoogleApiKey&address=x');

    expect(Redactor::redact($once))->toBe($once)
        ->and($once)->toBe('for https://maps.test/geocode/json?key=********iKey&address=x');
});

it('still masks credential parameters when no container config is bound', function () {
    $app = Container::getInstance();
    Container::setInstance(new Container);

    try {
        $text = Redactor::redact('for https://maps.test/geocode/json?key=GoogleApiKey');
    } finally {
        Container::setInstance($app);
    }

    expect($text)->toBe('for https://maps.test/geocode/json?key=********iKey');
});
