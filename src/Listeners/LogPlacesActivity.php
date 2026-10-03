<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Listeners;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;
use RoundlyConsulting\GooglePlaces\Support\ConfigValue;
use RoundlyConsulting\GooglePlaces\Support\Redactor;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Opt-in request logger wired to the package's lifecycle events. Neither event carries
 * the API key, and the failure message is redacted once more on the way out, so nothing
 * sensitive is ever written.
 *
 * @internal Wiring: the service provider subscribes it; toggle it with
 *           `google-places.logging.enabled`.
 */
final class LogPlacesActivity
{
    public function handleResponseReceived(PlacesResponseReceived $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->log()->info('google-places request', [
            'endpoint' => $event->endpoint,
            'http_status' => $event->httpStatus,
            'google_status' => $event->googleStatus,
            'duration_ms' => $event->durationMs,
        ]);
    }

    public function handleRequestFailed(PlacesRequestFailed $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->log()->warning('google-places request failed', [
            'endpoint' => $event->endpoint,
            'http_status' => $event->httpStatus,
            'google_status' => $event->googleStatus,
            'message' => Redactor::redact($event->message),
        ]);
    }

    private function enabled(): bool
    {
        return Config::boolean('google-places.logging.enabled');
    }

    /**
     * The `logging.channel` logger, or the default channel when it is not set —
     * absent, null or blank. A non-string channel throws instead of quietly logging
     * elsewhere.
     */
    private function log(): LoggerInterface
    {
        return Log::channel(ConfigValue::isSet(config('google-places.logging.channel'))
            ? Config::requireString('google-places.logging.channel')
            : null);
    }
}
