<?php

namespace Omnisearch\Tests;

use Omnisearch\DBAL\DriverFactory;
use Omnisearch\Memory\MemoryDriverFactory;
use Omnisearch\Testing\ContractDocument;
use Omnisearch\Testing\DriverContractTests;
use PHPUnit\Framework\TestCase;

/** The memory engine keeps the contract every engine keeps. */
final class MemoryDriverContractTest extends TestCase
{
    use DriverContractTests;

    protected function factory(): DriverFactory
    {
        return new MemoryDriverFactory();
    }

    protected function options(): array
    {
        return [];
    }

    protected function document(string $id, string $title, int $rank, array $labels): object
    {
        return new ContractDocument($id, $title, $rank, $labels);
    }
}
