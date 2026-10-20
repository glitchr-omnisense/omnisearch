<?php

namespace Omnisearch\Mapping;

/**
 * What a class's attributes say of its index: the index's name, the
 * connection it lives on, its fields in their order, the engine's options.
 * Plain values: it can be kept in a compiled container or a cache file
 * (toArray(), fromArray()).
 */
final class ClassMetadata
{
    /**
     * @param class-string                $class
     * @param array<string, FieldMetadata> $fields  by name, in their declared order
     * @param array<string, mixed>        $options the engine's own settings of the index
     */
    public function __construct(
        public readonly string $class,
        public readonly string $index,
        public readonly ?string $connection = null,
        public readonly array $fields = [],
        public readonly array $options = [],
    ) {
    }

    public function field(string $name): ?FieldMetadata
    {
        return $this->fields[$name] ?? null;
    }

    /** @return list<string> the fields that can be counted and filtered on */
    public function facets(): array
    {
        return array_keys(array_filter($this->fields, fn (FieldMetadata $f) => $f->facet));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['class' => $this->class, 'index' => $this->index, 'connection' => $this->connection, 'fields' => array_map(fn (FieldMetadata $f) => $f->toArray(), $this->fields), 'options' => $this->options];
    }

    /** @param array<string, mixed> $metadata */
    public static function fromArray(array $metadata): self
    {
        return new self($metadata['class'], $metadata['index'], $metadata['connection'] ?? null, array_map(FieldMetadata::fromArray(...), $metadata['fields'] ?? []), $metadata['options'] ?? []);
    }
}
