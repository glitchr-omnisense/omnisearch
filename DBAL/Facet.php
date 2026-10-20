<?php

namespace Omnisearch\DBAL;

/**
 * The values of a field among the documents found, with their counts.
 */
final class Facet
{
    /**
     * @param list<array{value: string, count: int, highlighted?: string}> $values in the engine's order (the most frequent first)
     * @param array<string, mixed>                                         $extra  what else the engine says of it
     */
    public function __construct(
        public readonly string $field,
        public readonly array $values,
        public readonly array $extra = [],
    ) {
    }
}
