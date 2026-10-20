<?php

namespace Omnisearch\Tests\Fixtures;

use Omnisearch\Mapping\Field;
use Omnisearch\Mapping\Index;
use Omnisearch\Mapping\Type;

/** A plain object: no persistence at all. */
#[Index('note')]
#[Field('text')]
#[Field('rank', type: Type::INT, sort: true)]
#[Field('labels', type: Type::STRINGS, facet: true)]
class Note
{
    /** @param list<string> $labels */
    public function __construct(public string $id, public string $text, public int $rank = 0, public array $labels = [])
    {
    }
}

