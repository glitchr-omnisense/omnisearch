<?php

namespace Omnisearch\Bridge\Symfony;

use Omnisearch\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * An engine whose own Symfony bundle hosts the indexes: the bundle creates
 * them, indexes on Doctrine's events and has its commands, and omnisearch
 * only searches through it. A driver package ships one (omnisearch/typesense:
 * glitchr/typesense-bundle), and OmnisearchBundle calls it before the
 * container is built so that the engine's bundle knows the indexes declared
 * by attributes as if its own configuration declared them; omnisearch's own
 * Doctrine listener then leaves that connection alone.
 */
interface Host
{
    /** Whether the engine's bundle is there to host (registered in this application). */
    public static function hosts(ContainerBuilder $builder): bool;

    /**
     * @param array<string, mixed> $options  the connection's options
     * @param list<ClassMetadata>  $metadata the indexes declared on that connection
     */
    public static function prepend(ContainerBuilder $builder, string $connection, array $options, array $metadata): void;
}
