<?php

namespace Omnisearch\DBAL;

use Omnisearch\Mapping\ClassMetadata;

/**
 * What an engine does for omnisearch, in the engine's own terms: a driver
 * (omnisearch/typesense, Omnisearch\Memory) turns the metadata into its
 * index, the objects into its documents, a Request into its query and its
 * answer into a Response.
 */
interface Driver
{
    /** The index described by $metadata, created where it is not (an existing one is left as it is). */
    public function create(ClassMetadata $metadata): void;

    /** The index dropped, with its documents; nothing when there is none. */
    public function drop(ClassMetadata $metadata): void;

    /**
     * The index as create() describes it to the engine, in the engine's
     * terms - to read, compare, or check against what the engine holds.
     *
     * @return array<string, mixed>
     */
    public function schema(ClassMetadata $metadata): array;

    /** The objects' documents written (created or replaced). */
    public function index(ClassMetadata $metadata, object ...$objects): void;

    /** The documents of these identifiers removed; an unknown one is no error. */
    public function remove(ClassMetadata $metadata, string ...$ids): void;

    /**
     * An engine that cannot be reached, or refuses, answers a Response with
     * its status and message rather than throwing.
     */
    public function search(ClassMetadata $metadata, Request $request): Response;
}
