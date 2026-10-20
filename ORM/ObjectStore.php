<?php

namespace Omnisearch\ORM;

use Omnisearch\Mapping\ClassMetadata;

/**
 * Where the objects behind the documents live (Doctrine through
 * Bridge\Doctrine, anything else): what identifies an object in its index,
 * and the objects of identifiers, back from a search.
 */
interface ObjectStore
{
    /** The identifier of an object's document. */
    public function identify(object $object): string;

    /**
     * @param list<string> $ids
     *
     * @return list<object> those found, in the order of $ids
     */
    public function load(ClassMetadata $metadata, array $ids, bool $cacheable = false): array;
}
