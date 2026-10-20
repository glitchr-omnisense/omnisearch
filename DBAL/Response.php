<?php

namespace Omnisearch\DBAL;

/**
 * What an engine answered: the documents found on this page, how many in
 * all, the facets counted; or why it could not (a status other than 200 and
 * the engine's message).
 */
final class Response
{
    /**
     * @param list<Hit>             $hits
     * @param array<string, Facet>  $facets by field
     * @param array<string, mixed>  $raw    the engine's own answer, without its hits
     */
    public function __construct(
        public readonly int $found = 0,
        public readonly int $page = 1,
        public readonly array $hits = [],
        public readonly array $facets = [],
        public readonly int $status = 200,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {
    }

    public static function failed(int $status, string $error): self
    {
        return new self(status: $status, error: $error);
    }

    public function ok(): bool
    {
        return 200 === $this->status;
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_map(fn (Hit $hit) => $hit->id, $this->hits);
    }
}
