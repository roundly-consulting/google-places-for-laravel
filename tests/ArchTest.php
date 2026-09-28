<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * The presets replace a single hand-written `dd`/`dump`/`ray` rule. That rule was worse
 * than it looked: Pest's arch layer only sees a dependency whose symbol *exists*, and
 * the `ray()` debugger package is not in the graph by policy — so `ray` was filtered out before the ban
 * ran and could never fail. `noDebuggingLeftovers` reads source tokens instead, which
 * don't care whether the function exists.
 */
ArchPresets::strictTypes('RoundlyConsulting\GooglePlaces');

/**
 * One exemption, and it is a real extension point rather than an oversight: PlacesException
 * is the package's exception base, and the typed Google failures are raised through its
 * named constructors. Hosts catch it; finalising it would close the hierarchy.
 *
 * No `swappableModelsAreNotFinal` counterweight here — google-places ships no Eloquent
 * model and no `*_model`-shaped config key, so there is no swap seam to pin. Verified
 * against `config/google-places.php`: every binding is a host, a field mask, or a limit.
 */
ArchPresets::finalByDefault('RoundlyConsulting\GooglePlaces', [PlacesException::class]);

/**
 * google-places does no cryptography — it is an HTTP client with an API key it never
 * hashes. The ban is a standing guard against a cache key or a signed photo URL being
 * hand-rolled here rather than in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\GooglePlaces');

/**
 * `modelsResolveThroughSeam` is REJECTED here, with the same cause jwt rejected it:
 * google-places ships no Eloquent model and no `*_model` config key, so BOTH halves are
 * structurally inert — the stray-literal half has no swap key to look for, and the
 * late-static-binding half no model to police. This is the pre-classification the plan
 * settled on (`Swap? > 0` adopts, `Swap? == 0` cannot), not a per-row re-litigation.
 */

/**
 * The Dependency Policy as a test. No `alsoAllow`: google-places' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`. If it goes
 * red, the graph is wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();

/**
 * google-places has no Models or Traits, but its `Concerns` namespace holds the
 * rate-limit trait — so the preset applies and pins that no concern reaches for
 * an action (it has none today; the pin keeps it that way).
 */
ArchPresets::modelsGoThroughTheFacade('RoundlyConsulting\GooglePlaces');
