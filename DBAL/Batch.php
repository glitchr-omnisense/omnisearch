<?php

namespace Omnisearch\DBAL;

use Omnisearch\Mapping\ClassMetadata;

/**
 * Writes gathered and sent together, like a transaction: objects to index
 * and identifiers to remove, per connection and index, in the order they
 * came (a removal after an indexing of the same document wins). commit()
 * sends them, consecutive writes of the same kind on the same index in one
 * call; rollBack() forgets them. A search engine has no transaction of its
 * own: what was sent before a failure stays sent.
 */
final class Batch
{
    /** @var list<array{0: Connection, 1: ClassMetadata, 2: 'index'|'remove', 3: object|string}> */
    private array $writes = [];

    public function index(Connection $connection, ClassMetadata $metadata, object $object): self
    {
        $this->writes[] = [$connection, $metadata, 'index', $object];

        return $this;
    }

    public function remove(Connection $connection, ClassMetadata $metadata, string $id): self
    {
        $this->writes[] = [$connection, $metadata, 'remove', $id];

        return $this;
    }

    public function count(): int
    {
        return \count($this->writes);
    }

    /**
     * @param ?callable(\Throwable, Connection, ClassMetadata): void $onError where a failed call goes;
     *                                                                       thrown when null
     *
     * @return int the writes sent
     */
    public function commit(?callable $onError = null): int
    {
        $writes = $this->writes;
        $this->writes = [];
        $sent = 0;

        $group = [];
        $flush = function () use (&$group, &$sent, $onError): void {
            if (!$group) {
                return;
            }
            [$connection, $metadata, $kind] = $group[0];
            $items = array_column($group, 3);
            try {
                'index' === $kind ? $connection->index($metadata, ...$items) : $connection->remove($metadata, ...$items);
                $sent += \count($items);
            } catch (\Throwable $e) {
                $onError ? $onError($e, $connection, $metadata) : throw $e;
            } finally {
                $group = [];
            }
        };

        foreach ($writes as $write) {
            if ($group && ($group[0][0] !== $write[0] || $group[0][1] !== $write[1] || $group[0][2] !== $write[2])) {
                $flush();
            }
            $group[] = $write;
        }
        $flush();

        return $sent;
    }

    public function rollBack(): void
    {
        $this->writes = [];
    }
}
