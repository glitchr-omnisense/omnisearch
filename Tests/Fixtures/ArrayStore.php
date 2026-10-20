<?php

namespace Omnisearch\Tests\Fixtures;

use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\ORM\ObjectStore;

/** The objects behind the documents in an array: identified by their public $id. */
final class ArrayStore implements ObjectStore
{
    /** @var array<string, object> */
    private array $objects = [];

    public function add(object ...$objects): self
    {
        foreach ($objects as $object) {
            $this->objects[$this->identify($object)] = $object;
        }

        return $this;
    }

    public function identify(object $object): string
    {
        return (string) $object->id;
    }

    public function load(ClassMetadata $metadata, array $ids, bool $cacheable = false): array
    {
        return array_values(array_filter(array_map(fn ($id) => $this->objects[$id] ?? null, $ids)));
    }
}
