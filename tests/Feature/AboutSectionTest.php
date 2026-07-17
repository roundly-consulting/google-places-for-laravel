<?php

declare(strict_types=1);

/**
 * A — the secret-safe `about` capture.
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous, and the leak was caught only
 * because one positive assertion happened to exist.
 *
 * google-places had no `about` test at all, and it is the package in this batch with a
 * real credential: a Google API key is billable. A leaked key is somebody else's Places
 * quota on this host's invoice, so `about` reports it as presence and never as a value.
 * The log channel is included because it names the host's own logging topology.
 *
 * `mustRender` is asserted BEFORE any secret check runs and throws at call time if empty,
 * so this cannot silently degrade into the purchases shape.
 */
it('renders the google-places section without leaking the api key', function (): void {
    config()->set('google-places.key', 'AIzaSyD-ThisIsABillableGoogleApiKey123456789');
    config()->set('google-places.cache.enabled', true);
    config()->set('google-places.logging.enabled', true);
    config()->set('google-places.logging.channel', 'acme-internal-ops');

    expect('google-places')->toLeakNoSecrets(
        secrets: [
            // The key is billable: rendering it puts the host's Google spend in any
            // screenshot of `php artisan about`.
            'AIzaSyD-ThisIsABillableGoogleApiKey123456789',
            // A partial leak is still a leak — pin the distinctive tail so a "masked"
            // renderer that prints the last N characters cannot pass.
            '123456789',
            // The log channel is the host's own topology, not google-places' business.
            'acme-internal-ops',
        ],
        mustRender: [
            // The positive proof each line reports rather than being silently empty.
            'API key',
            'SET',
            'Cache',
            'Logging',
            'ENABLED',
        ],
    );
});

/**
 * The switches render as switches, and a missing key reports as MISSING rather than as an
 * empty string. Kept separate: it is a rendering pin, not a leak pin, and folding it into
 * the case above would need the opposite config.
 */
it('reports a missing key and disabled switches in the about section', function (): void {
    config()->set('google-places.key', null);
    config()->set('google-places.cache.enabled', false);
    config()->set('google-places.logging.enabled', false);

    expect('google-places')->toLeakNoSecrets(
        secrets: ['AIzaSyD-ThisIsABillableGoogleApiKey123456789'],
        mustRender: ['MISSING', 'OFF'],
    );
});
