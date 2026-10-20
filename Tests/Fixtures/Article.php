<?php

namespace Omnisearch\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Omnisearch\Mapping\Field;
use Omnisearch\Mapping\Index;
use Omnisearch\Mapping\Type;

/**
 * An entity declaring its index: fields on the class (property paths), on
 * a property and on a method; it implements nothing of omnisearch.
 */
#[ORM\Entity]
#[ORM\Table(name: 'article')]
#[Index('article', options: ['typesense' => ['default_sorting_field' => 'publishedAt']])]
#[Field('title', type: Type::STRING, infix: true)]
#[Field('tags', type: Type::STRINGS, property: 'tags.slug', facet: true)]
#[Field('authors', type: Type::STRINGS, property: 'tags')]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, cascade: ['persist'])]
    private Collection $tags;

    public function __construct(
        #[ORM\Column] private string $title,
        #[ORM\Column] #[Field(type: Type::DATETIME, sort: true)] private \DateTimeImmutable $publishedAt,
        Tag ...$tags,
    ) {
        $this->tags = new ArrayCollection($tags);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    #[Field('audio', type: Type::BOOL, facet: true)]
    public function isAudible(): bool
    {
        return str_contains($this->title, 'music');
    }
}
