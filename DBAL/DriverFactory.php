<?php

namespace Omnisearch\DBAL;

/** Makes the drivers of one engine from a connection's options. */
interface DriverFactory
{
    /** The engine's name in a connection's configuration ("typesense", "memory"). */
    public function name(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options): Driver;
}
