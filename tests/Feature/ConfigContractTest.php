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
         * The three `per` leaves below ARE read — by `InteractsWithRateLimits::rateLimiter()`,
         * as `Config::enum("google-places.rate_limits.{$surface}.per", Timespan::class, …)`.
         * The scraper proves the sibling `limit` / `max_wait` / `jitter` / `enabled` /
         * `adaptive` reads through the same `{$surface}` interpolation, because it knows
         * `Config::integer()` / `Config::boolean()` as read methods; it does not know
         * `Config::enum()`. That is a gap in the scraper, not dead config: a `per` typo
         * throws (it used to become a minute), pinned by tests/Feature/StrictConfigTest.php.
         *
         * `allowUnread` is the assertion's own sanctioned escape here, and it does NOT blind
         * this check: every entry is rot-verified (a listed key that becomes readable, or was
         * never shipped, fails), so these three drop out the moment the scraper learns
         * `Config::enum()`, and a newly shipped unread key is still caught.
         */
        'allowUnread' => [
            'google-places.rate_limits.places.per',
            'google-places.rate_limits.routes.per',
            'google-places.rate_limits.geocoding.per',
        ],

        /*
         * Every `google-places.` literal in src is one of this package's own config keys (the
         * cache keys use `google-places:`, a colon), so the prefix rule cannot misattribute.
         * It is needed because the hosts and field masks are read through package-toolkit's
         * strict `Config::requireString()`, which the scraper does not know as a read: a
         * blank or non-string host / mask now throws instead of sending Google an empty
         * header or the request to the app's own origin. tests/Feature/StrictConfigTest.php
         * drives those reads.
         */
        'extraReadPrefixes' => ['google-places.'],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but this provider's `contributesToAbout()` closure calls
        // `config('google-places.key')`, `.cache.enabled` and `.logging.enabled` for real,
        // and the toolkit's `bindFromConfig()` reads more still. Excluding it would discard
        // the only reader of those keys and weaken the reverse direction for nothing.
    ]);
});
