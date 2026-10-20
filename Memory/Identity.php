<?php

namespace Omnisearch\Memory;

use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\ORM\ObjectStore;

/**
 * An object identified by its getId(), or its public $id; loads nothing
 * back. The memory engine's store when the application gives none.
 */
final class Identity implements ObjectStore
{
    public function identify(object $object): string
    {
        return (string) (method_exists($object, 'getId') ? $object->getId() : ($object->id ?? ''));
    }

    public function load(ClassMetadata $metadata, array $ids, bool $cacheable = false): array
    {
        return [];
    }
}
