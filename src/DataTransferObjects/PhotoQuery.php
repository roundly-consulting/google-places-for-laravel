<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

/**
 * One photo request as `GooglePlaces::fake()` records it: the photo's resource
 * name and the size it was asked for, plus whether the bytes or only the
 * key-free URL were wanted.
 */
final readonly class PhotoQuery
{
    public function __construct(
        public string $name,
        public int $maxWidth = 1600,
        public int $maxHeight = 1600,
        public bool $contents = true,
    ) {}
}
