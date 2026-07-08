<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Concerns;

use Closure;
use Illuminate\Http\Client\Response;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\HttpClientRateLimits\Enums\Timespan;
use RoundlyConsulting\HttpClientRateLimits\Exceptions\RateLimitExceededException;
use RoundlyConsulting\HttpClientRateLimits\Limit;
use RoundlyConsulting\HttpClientRateLimits\RateLimit;

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
        $config = config("google-places.rate_limits.{$surface}", []);

        if (($config['enabled'] ?? true) === false) {
            return null;
        }

        $timespan = Timespan::tryFrom((string) ($config['per'] ?? 'minute')) ?? Timespan::Minute;
        $owner = (string) config('google-places.rate_limits.owner', 'app');

        $rateLimit = RateLimit::make(new Limit(
            maxAttempts: (int) ($config['limit'] ?? 600),
            timespan: $timespan,
        ))->by("google-places:{$surface}:{$owner}");

        if (($config['adaptive'] ?? true) === true) {
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
     * exhaustion exception into a typed PlacesException so existing
     * `catch (PlacesException)` sites keep catching everything.
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
            throw PlacesException::rateLimited(
                surface: $surface,
                availableInSeconds: (int) ceil($exception->delayMs / 1000),
            );
        }
    }
}
