<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value looks like a Google Place ID — a non-empty token of
 * URL-safe base64 characters (e.g. "ChIJN1t_tDeuEmsRUsoyG83frY4").
 */
final class ValidPlaceId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[A-Za-z0-9_-]{6,512}$/', $value) !== 1) {
            $fail('The :attribute must be a valid Google Place ID.');
        }
    }
}
