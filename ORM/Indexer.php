<?php

namespace Omnisearch\ORM;

use Omnisearch\DBAL\Batch;

/**
 * The indexing rule, independent of any persistence's events: an object of
 * a declared class that is persisted or updated is indexed, one that is
 * removed is removed from its index - its identifier taken before it goes
 * (removing()), the removal written after (removed()) - all of it sent on
 * flush(). A persistence's bridge calls it from its events
 * (Bridge\Doctrine\IndexListener for Doctrine's).
 *
 * A failure to reach an engine does not stop the application's flush: it is
 * handed to $onError, or thrown when there is none.
 *
 * $connections limits it to some connections: those whose engine is
 * indexed by another listener (a Typesense collection under the bundle's
 * own listener) are left out.
 */
final class Indexer
{
    private Batch $batch;

    /** @var \WeakMap<object, string> */
    private \WeakMap $removing;

    /**
     * @param ?callable(\Throwable): void $onError
     * @param ?list<string>               $connections null: all of them
     */
    public function __construct(
        private readonly Manager $manager,
        private readonly mixed $onError = null,
        private readonly ?array $connections = null,
    ) {
        $this->batch = new Batch();
        $this->removing = new \WeakMap();
    }

    public function supports(object $object): bool
    {
        $metadata = $this->manager->metadata()->find($object);

        return null !== $metadata && (null === $this->connections || \in_array($this->manager->connection($metadata)->name, $this->connections, true));
    }

    public function persisted(object $object): void
    {
        if ($this->supports($object)) {
            $metadata = $this->manager->classMetadata($object);
            $this->batch->index($this->manager->connection($metadata), $metadata, $object);
        }
    }

    public function updated(object $object): void
    {
        $this->persisted($object);
    }

    /** Before the object is gone: its identifier is taken now. */
    public function removing(object $object): void
    {
        if ($this->supports($object) && $this->manager->store()) {
            $this->removing[$object] = $this->manager->store()->identify($object);
        }
    }

    public function removed(object $object): void
    {
        if (!isset($this->removing[$object])) {
            return;
        }
        $metadata = $this->manager->classMetadata($object);
        $this->batch->remove($this->manager->connection($metadata), $metadata, $this->removing[$object]);
        unset($this->removing[$object]);
    }

    /** @return int the writes sent */
    public function flush(): int
    {
        if (!$this->batch->count()) {
            return 0;
        }
        $onError = $this->onError ? fn (\Throwable $e) => ($this->onError)($e) : null;

        return $this->batch->commit($onError);
    }

    public function clear(): void
    {
        $this->batch->rollBack();
        $this->removing = new \WeakMap();
    }
}
