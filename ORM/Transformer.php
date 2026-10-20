<?php

namespace Omnisearch\ORM;

use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\Mapping\FieldMetadata;
use Omnisearch\Mapping\Type;

/**
 * An object into its document, by its index's fields: each value read by
 * its property path and given its field's type (a date as a timestamp, a
 * list as a list), the identifier as "id". For the drivers that hold plain
 * documents; an engine's own package may convert objects itself.
 */
final class Transformer
{
    public function __construct(private readonly ObjectStore $store)
    {
    }

    /** @return array<string, mixed> */
    public function document(ClassMetadata $metadata, object $object): array
    {
        $document = ['id' => $this->store->identify($object)];
        foreach ($metadata->fields as $name => $field) {
            $document[$name] = self::value($field, PropertyReader::read($object, $field->path(), Type::isList($field->type)));
        }

        return $document;
    }

    public static function value(FieldMetadata $field, mixed $value): mixed
    {
        if (\is_array($value)) {
            $type = substr($field->type, 0, -2) ?: Type::STRING;

            return array_values(array_map(fn ($v) => self::scalar($type, $v), array_filter($value, fn ($v) => null !== $v && '' !== $v)));
        }

        return null === $value && $field->optional ? null : self::scalar($field->type, $value);
    }

    private static function scalar(string $type, mixed $value): mixed
    {
        return match ($type) {
            Type::DATETIME => $value instanceof \DateTimeInterface ? $value->getTimestamp() : (int) $value,
            Type::INT, Type::BIGINT => (int) $value,
            Type::FLOAT => (float) $value,
            Type::BOOL => (bool) $value,
            Type::GEOPOINT => \is_array($value) ? array_map('floatval', $value) : $value,
            default => \is_object($value) && method_exists($value, '__toString') ? (string) $value : (string) ($value ?? ''),
        };
    }
}
