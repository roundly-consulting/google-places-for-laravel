<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Concerns;

use Closure;
use Illuminate\Http\Client\Response;
use RoundlyConsulting\GooglePlaces\Exceptions\RateLimitExceededException as PlacesRateLimitExceededException;
use RoundlyConsulting\GooglePlaces\Support\ConfigValue;
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
     *
     * Every key is read by its full name through package-toolkit's strict readers,
     * so a bad value throws InvalidConfigurationException naming e.g.
     * `google-places.rate_limits.places.limit`. Only a key that is not set — absent,
     * null or blank (a host's `KEY=`) — takes its default: `(int) 'lots'` used to be
     * 0, a junk `max_wait` / `jitter` was dropped and a `per` typo quietly became a
     * minute.
     */
    protected function rateLimiter(string $surface): ?RateLimit
    {
        // Pin the surface to the section actually read, so every key below is a
        // shipped one. The keys stay spelled out (`rate_limits.{$surface}.limit`) so the
        // config contract can match each against the shipped file.
        $surface = match ($surface) {
            'routes', 'geocoding' => $surface,
            default => 'places',
        };

        if (! Config::boolean("google-places.rate_limits.{$surface}.enabled", true)) {
            return null;
        }

        $owner = ConfigValue::isSet(config('google-places.rate_limits.owner'))
            ? Config::requireString('google-places.rate_limits.owner')
            : 'app';

        $rateLimit = RateLimits::make(new Limit(
            maxAttempts: Config::integer("google-places.rate_limits.{$surface}.limit", 600, min: 1),
            timespan: Config::enum("google-places.rate_limits.{$surface}.per", Timespan::class, Timespan::Minute),
        ))->by("google-places:{$surface}:{$owner}");

        if (Config::boolean("google-places.rate_limits.{$surface}.adaptive", true)) {
            $rateLimit->adaptive();
        }

        if (ConfigValue::isSet(config("google-places.rate_limits.{$surface}.max_wait"))) {
            $rateLimit->maxWait(Config::integer("google-places.rate_limits.{$surface}.max_wait", 0, min: 0));
        }

        if (ConfigValue::isSet(config("google-places.rate_limits.{$surface}.jitter"))) {
            $rateLimit->jitter(Config::integer("google-places.rate_limits.{$surface}.jitter", 0, min: 0));
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
