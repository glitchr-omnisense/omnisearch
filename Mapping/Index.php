<?php

namespace Omnisearch\Mapping;

/**
 * A class whose objects are documents of a search index - declared on the
 * class itself, whatever the engine behind:
 *
 *     #[Index('article')]
 *     #[Field('title', type: Type::STRINGS, property: 'translations.title')]
 *     #[Field('tags', type: Type::STRINGS, property: 'tags.slug', facet: true)]
 *     class Article { ... }
 *
 * $options are the engine's own settings of the index (for Typesense:
 * default_sorting_field, token_separators, symbols_to_index), read by its
 * driver, ignored by the others.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Index
{
    /**
     * @param string               $name       the index's name for the application (an engine may
     *                                         put a prefix in front of it on its server)
     * @param ?string              $connection the connection it lives on; the default one when null
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $connection = null,
        public readonly array $options = [],
    ) {
    }
}
