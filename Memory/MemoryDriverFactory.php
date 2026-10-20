<?php

namespace Omnisearch\Memory;

use Omnisearch\DBAL\Driver;
use Omnisearch\DBAL\DriverFactory;
use Omnisearch\ORM\ObjectStore;
use Omnisearch\ORM\Transformer;

/** The "memory" engine: MemoryDriver, with the store that identifies the objects (their getId() by default). */
final class MemoryDriverFactory implements DriverFactory
{
    public function __construct(private readonly ObjectStore $store = new Identity())
    {
    }

    public function name(): string
    {
        return 'memory';
    }

    public function create(array $options): Driver
    {
        return new MemoryDriver(new Transformer($this->store));
    }
}
