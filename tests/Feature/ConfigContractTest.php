<?php

declare(strict_types=1);

/**
 * C — the config-key contract, pinned in both directions.
 *
 * The hand-written `ConfigTest` beside this file spot-checks nine defaults it happens to
 * name. That is a sample, not a contract: it cannot see a key the code reads but the file
 * never ships (shops #18 — a whole feature reading `shops.payments.*` against a file that
 * shipped `payment.*`, green because the suite set the same wrong key), nor a key the file
 * ships that nothing reads (alerts #24; media #27's `max_file_size` cap that never applied
 * — an upload endpoint with no size limit). This expectation scrapes reads from source
 * **tokens**, so a docblock mention of a key is a comment and never a read.
 *
 * Adopting it found three interpolated key reads — `field_masks.{$endpoint}`,
 * `hosts.{$service}` and `rate_limits.{$surface}` — none of which could be checked against
 * the shipped file at all. The first two are fixed in src: every call site already passed a
 * literal, so the interpolation bought nothing and an exhaustive `match` now names each key
 * (and throws on an unknown surface instead of silently returning `''`, which used to send
 * the request to the app's own origin). That follows cosmos-foundation's precedent for the
 * same shape.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/google-places.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        /*
         * The 18 rate-limit leaves below are read — by `InteractsWithRateLimits::rateLimiter()`,
         * which resolves one surface's section and then offsets into it (`$config['enabled']`,
         * `$config['limit']`, …). The scraper cannot attribute them, and that is a limitation
         * of the scraper rather than dead config:
         *
         *   - `sectionVariables` is the documented remedy for exactly this shape, but it maps
         *     one variable to ONE prefix. Here a single `$config` is, by design, whichever of
         *     the three sibling sections the surface selected — so at most 6 of the 18 could
         *     ever be attributed.
         *   - The only alternatives are to unroll 18 literal leaf reads into what is now one
         *     line, or to duplicate the method three times. Both are strictly worse code
         *     written to satisfy a test, and the fleet's own lesson is that a test harness
         *     does not get to dictate shipped code (the `down()` pin went 30/30 red against a
         *     complying fleet before it was deleted).
         *
         * `allowUnread` is the assertion's own sanctioned escape here, and it does NOT blind
         * this check: every entry is rot-verified (a listed key that becomes readable, or was
         * never shipped, fails), and a *newly* shipped unread key is not in the list, so it
         * still fails. What is given up is static proof of these 18 reads specifically —
         * covered instead by tests/Feature/RateLimitTest.php, which drives all three surfaces
         * through the real limiter.
         *
         * Reported upstream: `sectionVariables` accepting a list of prefixes would close this
         * truthfully. The shape is not unique to google-places — `geolocation`, `git`,
         * `kubernetes-api` and `plausible` all ship the same `InteractsWithRateLimits` trait
         * with the same section-then-offset read (5 packages, past the ">3 = defect" bar).
         */
        'allowUnread' => [
            'google-places.rate_limits.places.enabled',
            'google-places.rate_limits.places.limit',
            'google-places.rate_limits.places.per',
            'google-places.rate_limits.places.adaptive',
            'google-places.rate_limits.places.max_wait',
            'google-places.rate_limits.places.jitter',
            'google-places.rate_limits.routes.enabled',
            'google-places.rate_limits.routes.limit',
            'google-places.rate_limits.routes.per',
            'google-places.rate_limits.routes.adaptive',
            'google-places.rate_limits.routes.max_wait',
            'google-places.rate_limits.routes.jitter',
            'google-places.rate_limits.geocoding.enabled',
            'google-places.rate_limits.geocoding.limit',
            'google-places.rate_limits.geocoding.per',
            'google-places.rate_limits.geocoding.adaptive',
            'google-places.rate_limits.geocoding.max_wait',
            'google-places.rate_limits.geocoding.jitter',
        ],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but this provider's `contributesToAbout()` closure calls
        // `config('google-places.key')`, `.cache.enabled` and `.logging.enabled` for real,
        // and the toolkit's `bindFromConfig()` reads more still. Excluding it would discard
        // the only reader of those keys and weaken the reverse direction for nothing.
    ]);
});
