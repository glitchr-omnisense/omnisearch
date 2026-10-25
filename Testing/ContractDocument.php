<?php

namespace Omnisearch\Testing;

use Omnisearch\Mapping\Field;
use Omnisearch\Mapping\Index;
use Omnisearch\Mapping\Type;

/**
 * The document DriverContractTests indexes, for an engine that indexes any
 * object (the memory engine's Identity store reads its id).
 */
#[Index('omnisearch_contract')]
#[Field('title', type: Type::STRING)]
#[Field('rank', type: Type::INT, sort: true)]
#[Field('labels', type: Type::STRINGS, facet: true)]
final class ContractDocument
{
    /** @param list<string> $labels */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly int $rank = 0,
        public readonly array $labels = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
