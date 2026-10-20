<?php

namespace Omnisearch\Mapping;

/**
 * A field of the class's index. On the class it names the field and the
 * property path its value is read from ("translations.title", "tags.slug",
 * "isAudible"); on a property or a method the name and the path default to
 * that property's (a method's name without get/is/has).
 *
 * - $type: one of Type's - the engine maps it onto its own;
 * - $facet: its values can be counted and filtered on;
 * - $sort: results can be sorted by it (numbers and dates always can on
 *   most engines);
 * - $infix: it can be matched by the middle of a word, where the engine can;
 * - $optional: a document may lack it;
 * - $locale: the language its text is tokenized in;
 * - $options: the engine's own field settings, by engine name or flat.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Field
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly string $type = Type::STRING,
        public readonly ?string $property = null,
        public readonly bool $facet = false,
        public readonly bool $sort = false,
        public readonly bool $infix = false,
        public readonly bool $optional = false,
        public readonly ?string $locale = null,
        public readonly array $options = [],
    ) {
    }
}
