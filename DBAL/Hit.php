<?php

namespace Omnisearch\DBAL;

/**
 * A document found: its identifier, its fields as the engine holds them,
 * and per field the words of the term it matched there.
 */
final class Hit
{
    /**
     * @param array<string, mixed>        $document
     * @param array<string, list<string>> $highlights the matched words, by field, in the engine's order
     * @param array<string, mixed>        $raw        the engine's own hit, as it gave it
     */
    public function __construct(
        public readonly string $id,
        public readonly array $document,
        public readonly array $highlights = [],
        public readonly ?float $score = null,
        public readonly array $raw = [],
    ) {
    }

    /** @return list<string> every matched word, field after field */
    public function matchedTokens(): array
    {
        $tokens = [];
        $highlights = $this->highlights;
        array_walk_recursive($highlights, function ($token) use (&$tokens) {
            $tokens[] = $token;
        });

        return $tokens;
    }
}
