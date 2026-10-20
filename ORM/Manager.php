<?php

namespace Omnisearch\ORM;

use Omnisearch\DBAL\Connection;
use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\Registry;

/**
 * The application's search, the way an EntityManager is its database: the
 * indexes its classes declare, the connections they live on, a finder per
 * index, the indexing of objects.
 *
 *     $manager = new Manager($registry, MetadataRegistry::fromDirectories([__DIR__.'/src/Entity']), $store);
 *     $manager->finder(Article::class)->search((new Query('fuji'))->in('title'))->objects();
 */
final class Manager
{
    /** @var array<string, Finder> */
    private array $finders = [];

    public function __construct(
        private readonly Registry $registry,
        private readonly MetadataRegistry $metadata,
        private readonly ?ObjectStore $store = null,
    ) {
    }

    public function registry(): Registry
    {
        return $this->registry;
    }

    public function metadata(): MetadataRegistry
    {
        return $this->metadata;
    }

    public function store(): ?ObjectStore
    {
        return $this->store;
    }

    /** The metadata of an index, by its name, a class or an object. */
    public function classMetadata(string|object $indexOrClass): ClassMetadata
    {
        return $this->metadata->get($indexOrClass);
    }

    /** The connection an index lives on. */
    public function connection(string|object $indexOrClass): Connection
    {
        $metadata = $indexOrClass instanceof ClassMetadata ? $indexOrClass : $this->metadata->get($indexOrClass);

        return $this->registry->connection($metadata->connection);
    }

    public function finder(string|object $indexOrClass): Finder
    {
        $metadata = $this->metadata->get($indexOrClass);

        return $this->finders[$metadata->index] ??= new Finder($this->connection($metadata), $metadata, $this->store);
    }

    /** Every index created where it is not. */
    public function create(): void
    {
        foreach ($this->metadata->all() as $metadata) {
            $this->connection($metadata)->create($metadata);
        }
    }

    /** Every index dropped. */
    public function drop(): void
    {
        foreach ($this->metadata->all() as $metadata) {
            $this->connection($metadata)->drop($metadata);
        }
    }

    /** These objects indexed now, each in its class's index. */
    public function index(object ...$objects): void
    {
        foreach ($objects as $object) {
            $metadata = $this->metadata->get($object);
            $this->connection($metadata)->index($metadata, $object);
        }
    }
}
