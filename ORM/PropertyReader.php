<?php

namespace Omnisearch\ORM;

/**
 * Reads a value from an object by its property path, without a framework:
 * "title", "translations.title" (through a list - an array, a
 * Traversable such as a Doctrine collection: every item), "isAudible" (a
 * method of that name). A segment is read by its getter (get, is, has),
 * a method of its very name, a public property, or an ArrayAccess key;
 * what cannot be read is null. A list at the end is an array whose objects
 * are their __toString(); an array field's value is flattened, a single
 * value of one wrapped.
 */
final class PropertyReader
{
    public static function read(object $object, string $path, bool $list = false): mixed
    {
        $value = $object;
        foreach (explode('.', $path) as $segment) {
            if (null === $value || false === $value) {
                break;
            }
            $value = is_iterable($value)
                ? array_map(fn ($item) => self::segment($item, $segment), self::items($value))
                : self::segment($value, $segment);
        }

        if (is_iterable($value)) {
            $value = array_map(fn ($item) => \is_object($item) && method_exists($item, '__toString') ? (string) $item : $item, self::items($value));
        }
        if ($list && !\is_array($value)) {
            $value = [$value];
        }
        if (\is_array($value)) {
            $flat = [];
            array_walk_recursive($value, function ($item) use (&$flat) {
                $flat[] = $item;
            });

            return $flat;
        }

        return $value;
    }

    /** @return list<mixed> */
    private static function items(iterable $value): array
    {
        return array_values(\is_array($value) ? $value : iterator_to_array($value, false));
    }

    private static function segment(mixed $value, string $segment): mixed
    {
        if (!\is_object($value)) {
            return \is_array($value) ? ($value[$segment] ?? null) : null;
        }
        $camel = ucfirst($segment);
        foreach (['get'.$camel, 'is'.$camel, 'has'.$camel, $segment] as $method) {
            if (method_exists($value, $method) && (new \ReflectionMethod($value, $method))->isPublic()) {
                return $value->{$method}();
            }
        }
        if (property_exists($value, $segment) && (new \ReflectionProperty($value, $segment))->isPublic()) {
            return $value->{$segment} ?? null;
        }
        if ($value instanceof \ArrayAccess) {
            return $value[$segment] ?? null;
        }
        if (method_exists($value, '__get')) {
            return $value->{$segment};
        }

        return null;
    }
}
