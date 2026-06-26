<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use Illuminate\Support\Collection;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

/**
 * A full origins × destinations distance matrix from the Routes API.
 */
final readonly class DistanceMatrix
{
    /**
     * @param  list<MatrixElement>  $elements
     */
    public function __construct(
        public array $elements,
        public int $originCount,
        public int $destinationCount,
        public TravelMode $type = TravelMode::Driving,
    ) {}

    /**
     * @param  array<int, mixed>  $elements
     */
    public static function fromRoutesElements(array $elements, int $originCount, int $destinationCount, TravelMode $type = TravelMode::Driving): self
    {
        $mapped = array_map(
            static fn (mixed $element): MatrixElement => MatrixElement::fromRoutesElement((array) $element, $type),
            array_values($elements),
        );

        usort(
            $mapped,
            static fn (MatrixElement $a, MatrixElement $b): int => [$a->originIndex, $a->destinationIndex] <=> [$b->originIndex, $b->destinationIndex],
        );

        return new self($mapped, $originCount, $destinationCount, $type);
    }

    /**
     * The element for a specific origin/destination pair.
     */
    public function for(int $originIndex, int $destinationIndex): ?MatrixElement
    {
        foreach ($this->elements as $element) {
            if ($element->originIndex === $originIndex && $element->destinationIndex === $destinationIndex) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Every element for a given origin, keyed by destination index.
     *
     * @return Collection<int, MatrixElement>
     */
    public function origin(int $originIndex): Collection
    {
        return collect($this->elements)
            ->filter(static fn (MatrixElement $element): bool => $element->originIndex === $originIndex)
            ->keyBy(static fn (MatrixElement $element): int => $element->destinationIndex);
    }

    /**
     * Every element for a given destination, keyed by origin index.
     *
     * @return Collection<int, MatrixElement>
     */
    public function destination(int $destinationIndex): Collection
    {
        return collect($this->elements)
            ->filter(static fn (MatrixElement $element): bool => $element->destinationIndex === $destinationIndex)
            ->keyBy(static fn (MatrixElement $element): int => $element->originIndex);
    }
}
