<?php

namespace Omnisearch\DBAL;

use Omnisearch\Mapping\ClassMetadata;

/**
 * A named engine: the driver a connection's configuration made, and the
 * calls an application makes on it.
 */
final class Connection
{
    public function __construct(
        public readonly string $name,
        private readonly Driver $driver,
    ) {
    }

    public function driver(): Driver
    {
        return $this->driver;
    }

    public function create(ClassMetadata $metadata): void
    {
        $this->driver->create($metadata);
    }

    public function drop(ClassMetadata $metadata): void
    {
        $this->driver->drop($metadata);
    }

    /** @return array<string, mixed> */
    public function schema(ClassMetadata $metadata): array
    {
        return $this->driver->schema($metadata);
    }

    public function index(ClassMetadata $metadata, object ...$objects): void
    {
        if ($objects) {
            $this->driver->index($metadata, ...$objects);
        }
    }

    public function remove(ClassMetadata $metadata, string ...$ids): void
    {
        if ($ids) {
            $this->driver->remove($metadata, ...$ids);
        }
    }

    public function search(ClassMetadata $metadata, Request $request): Response
    {
        return $this->driver->search($metadata, $request);
    }
}
