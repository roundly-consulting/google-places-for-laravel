<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Support;

use Illuminate\Container\Container;
use SensitiveParameter;

/**
 * Masks credentials in any text that leaves the client — exception messages, event
 * payloads, log lines, health-check details.
 *
 * The Geocoding API takes its key as a `key=` query parameter, and a transport failure's
 * message (Laravel's ConnectionException) ends in the full request URL. Google may also echo
 * a key back in an error body. So every message is scrubbed twice: the configured key is
 * masked wherever it appears (raw or URL-encoded), and the value of any credential query
 * parameter is masked whatever it holds (a proxy host, an overridden key, a signature).
 * A mask keeps the last four characters so an operator can still tell which key was used.
 *
 * @internal Used by PlacesException, PlacesRequestFailed, the logging listener and check().
 */
final class Redactor
{
    private const string CREDENTIAL_PARAMETER = '/([?&](?:key|api_key|apikey|access_token|token|signature|client_secret)=)([^&\s#"\'<>]+)/i';

    public static function redact(string $text, #[SensitiveParameter] ?string $key = null): string
    {
        $key ??= self::configuredKey();

        if ($key !== null && $key !== '') {
            $text = str_replace(
                array_values(array_unique([$key, rawurlencode($key), urlencode($key)])),
                self::mask($key),
                $text,
            );
        }

        return preg_replace_callback(
            self::CREDENTIAL_PARAMETER,
            static fn (array $match): string => $match[1].self::mask(rawurldecode($match[2])),
            $text,
        ) ?? $text;
    }

    /**
     * Stars for all but the last four characters; a value too short to keep a tail of
     * without giving most of it away is starred out completely.
     */
    public static function mask(#[SensitiveParameter] string $secret): string
    {
        $length = strlen($secret);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($secret, -4);
    }

    private static function configuredKey(): ?string
    {
        $container = Container::getInstance();

        if (! $container->bound('config')) {
            return null;
        }

        $key = $container->make('config')->get('google-places.key');

        return is_string($key) && ConfigValue::isSet($key) ? $key : null;
    }
}
