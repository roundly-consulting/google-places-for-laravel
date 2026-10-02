<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Concerns;

use Closure;
use Illuminate\Http\Client\Response;
use RoundlyConsulting\GooglePlaces\Exceptions\RateLimitExceededException as PlacesRateLimitExceededException;
use RoundlyConsulting\HttpClientRateLimits\Enums\Timespan;
use RoundlyConsulting\HttpClientRateLimits\Exceptions\RateLimitExceededException;
use RoundlyConsulting\HttpClientRateLimits\Facades\RateLimits;
use RoundlyConsulting\HttpClientRateLimits\Limit;
use RoundlyConsulting\HttpClientRateLimits\RateLimit;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Client-side outbound rate limiting for the direct Google Places client.
 *
 * Every outbound call funnels through a per-surface budget keyed
 * `google-places:{surface}:{owner}` (surface ∈ places|routes|geocoding), read
 * from the `google-places.rate_limits.{surface}` config block. Requests are
 * paced (the limiter waits for the window to free up) rather than hard-failing;
 * set a `max_wait` to fail fast instead. With `adaptive` on (the default) a 429
 * Retry-After from Google self-tunes the limiter.
 */
trait InteractsWithRateLimits
{
    /**
     * Build the client-side rate limiter for an API surface from its config, or
     * null when the host has disabled throttling for it.
     */
    protected function rateLimiter(string $surface): ?RateLimit
    {
        /** @var array<string, mixed> $config */
        $config = match ($surface) {
            'routes' => config('google-places.rate_limits.routes', []),
            'geocoding' => config('google-places.rate_limits.geocoding', []),
            default => config('google-places.rate_limits.places', []),
        };

        if (! Config::for($config)->boolean('enabled', true)) {
            return null;
        }

        $timespan = Timespan::tryFrom((string) ($config['per'] ?? 'minute')) ?? Timespan::Minute;
        $owner = (string) config('google-places.rate_limits.owner', 'app');

        $rateLimit = RateLimits::make(new Limit(
            maxAttempts: (int) ($config['limit'] ?? 600),
            timespan: $timespan,
        ))->by("google-places:{$surface}:{$owner}");

        if (Config::for($config)->boolean('adaptive', true)) {
            $rateLimit->adaptive();
        }

        if (isset($config['max_wait']) && is_numeric($config['max_wait'])) {
            $rateLimit->maxWait((int) $config['max_wait']);
        }

        if (isset($config['jitter']) && is_numeric($config['jitter'])) {
            $rateLimit->jitter((int) $config['jitter']);
        }

        return $rateLimit;
    }

    /**
     * Send a request through its surface's rate limiter, translating hcrl's own
     * exhaustion exception into the package's RateLimitExceededException — a
     * PlacesException (so `catch (PlacesException)` sites keep catching
     * everything) that also carries the toolkit's HasRetryAfter hint.
     *
     * @param  Closure(): Response  $send
     */
    protected function throttled(string $surface, Closure $send): Response
    {
        $rateLimit = $this->rateLimiter($surface);

        if ($rateLimit === null) {
            return $send();
        }

        try {
            /** @var Response $response */
            $response = $rateLimit->handle($send);

            return $response;
        } catch (RateLimitExceededException $exception) {
            throw PlacesRateLimitExceededException::for(
                surface: $surface,
                retryAfterSeconds: (int) ceil($exception->delayMs / 1000),
            );
        }
    }
}
