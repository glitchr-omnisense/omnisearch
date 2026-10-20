<?php

namespace Omnisearch\Bridge\Doctrine;

use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Omnisearch\ORM\Indexer;

/**
 * The indexing rule on Doctrine's events: register it on an EventManager
 * (self::EVENTS), or let Bridge\Symfony do it.
 *
 *     $entityManager->getEventManager()->addEventListener(IndexListener::EVENTS, new IndexListener($indexer));
 */
final class IndexListener
{
    public const EVENTS = [Events::postPersist, Events::postUpdate, Events::preRemove, Events::postRemove, Events::postFlush];

    public function __construct(private readonly Indexer $indexer)
    {
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->indexer->persisted($args->getObject());
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->indexer->updated($args->getObject());
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $this->indexer->removing($args->getObject());
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->indexer->removed($args->getObject());
    }

    public function postFlush(): void
    {
        $this->indexer->flush();
    }
}
