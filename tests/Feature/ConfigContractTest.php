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
        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but this provider's `contributesToAbout()` closure calls
        // `config('google-places.key')`, `.cache.enabled` and `.logging.enabled` for real,
        // and the toolkit's `bindFromConfig()` reads more still. Excluding it would discard
        // the only reader of those keys and weaken the reverse direction for nothing.
    ]);
});
