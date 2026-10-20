<?php

namespace Omnisearch\Mapping;

/**
 * A field of an index, as the attributes declared it: its name in the
 * document, the property path its value is read from, its type and options.
 */
final class FieldMetadata
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $name,
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

    /** The property path the value is read from: the field's name when none is given. */
    public function path(): string
    {
        return $this->property ?? $this->name;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $field */
    public static function fromArray(array $field): self
    {
        return new self(...$field);
    }
}
