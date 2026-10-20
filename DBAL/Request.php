<?php

namespace Omnisearch\DBAL;

/**
 * A search, as every engine is asked it: words in some fields, conditions,
 * facets to count, an order, a page. $options carry what only one engine
 * knows (Typesense's infix, num_typos, pinned_hits...), by its own names:
 * a driver reads those it knows and leaves the others.
 */
final class Request
{
    /**
     * @param list<string>                                    $fields  the fields the term is searched in
     * @param list<Filter>                                    $filters all of them must hold
     * @param list<string>                                    $facets  the fields whose values are counted
     * @param list<array{0: string, 1: 'asc'|'desc'}>         $sort    by field, in order
     * @param array<string, mixed>                            $options
     */
    public function __construct(
        public readonly ?string $term = null,
        public readonly array $fields = [],
        public readonly array $filters = [],
        public readonly array $facets = [],
        public readonly ?int $maxFacetValues = null,
        public readonly array $sort = [],
        public readonly int $page = 1,
        public readonly ?int $perPage = null,
        public readonly array $options = [],
        public readonly bool $cacheable = false,
    ) {
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }
}
