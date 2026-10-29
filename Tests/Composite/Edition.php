<?php

namespace Omnisearch\Tests\Composite;

use Doctrine\ORM\Mapping as ORM;
use Omnisearch\Mapping\Field;
use Omnisearch\Mapping\Index;

/** An entity with a composite identifier, one of its values holding the separator itself. */
#[ORM\Entity]
#[ORM\Table(name: 'edition')]
#[Index('edition')]
#[Field('title')]
class Edition
{
    public function __construct(
        #[ORM\Id] #[ORM\Column] private string $isbn,
        #[ORM\Id] #[ORM\Column] private int $printing,
        #[ORM\Column] private string $title,
    ) {
    }

    public function getIsbn(): string
    {
        return $this->isbn;
    }

    public function getPrinting(): int
    {
        return $this->printing;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
