<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Listeners;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;

/**
 * Opt-in request logger wired to the package's lifecycle events. The API key is
 * never present on either event, so nothing sensitive is ever written.
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
            'message' => $event->message,
        ]);
    }

    private function enabled(): bool
    {
        return (bool) config('google-places.logging.enabled', false);
    }

    private function log(): LoggerInterface
    {
        $channel = config('google-places.logging.channel');

        return Log::channel(is_string($channel) && $channel !== '' ? $channel : null);
    }
}
