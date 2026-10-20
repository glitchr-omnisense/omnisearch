<?php

namespace Omnisearch\Mapping;

/**
 * The types of a field, the same for every engine; a driver maps each onto
 * its own (Typesense: int32, int64, string[]...). A type ending in "[]" is a
 * list of that type.
 */
final class Type
{
    public const STRING = 'string';
    public const STRINGS = 'string[]';
    public const INT = 'int';
    public const INTS = 'int[]';
    public const BIGINT = 'bigint';
    public const FLOAT = 'float';
    public const FLOATS = 'float[]';
    public const BOOL = 'bool';
    public const BOOLS = 'bool[]';
    /** A date and time: a timestamp in the document. */
    public const DATETIME = 'datetime';
    /** [latitude, longitude] */
    public const GEOPOINT = 'geopoint';

    public const ALL = [self::STRING, self::STRINGS, self::INT, self::INTS, self::BIGINT, self::FLOAT, self::FLOATS, self::BOOL, self::BOOLS, self::DATETIME, self::GEOPOINT];

    public static function isList(string $type): bool
    {
        return str_ends_with($type, '[]');
    }
}
