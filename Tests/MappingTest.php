<?php

namespace Omnisearch\Tests;

use Omnisearch\Exception\MappingException;
use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\Mapping\Field;
use Omnisearch\Mapping\Index;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\Mapping\Type;
use Omnisearch\Tests\Fixtures\Article;
use Omnisearch\Tests\Fixtures\Draft;
use Omnisearch\Tests\Fixtures\Note;
use Omnisearch\Tests\Fixtures\Tag;
use PHPUnit\Framework\TestCase;

final class MappingTest extends TestCase
{
    public function testTheAttributesAreReadInTheirOrder(): void
    {
        $metadata = AttributeLoader::load(Article::class);

        self::assertSame('article', $metadata->index);
        self::assertNull($metadata->connection);
        self::assertSame(['title', 'tags', 'authors', 'publishedAt', 'audio'], array_keys($metadata->fields), 'the class\'s, then the properties\', then the methods\'');
        self::assertSame([null, 'tags.slug', 'tags', 'publishedAt', 'isAudible'], array_map(fn ($f) => $f->property, array_values($metadata->fields)));
        self::assertSame('title', $metadata->fields['title']->path());
        self::assertTrue($metadata->fields['title']->infix);
        self::assertTrue($metadata->fields['publishedAt']->sort);
        self::assertSame(Type::DATETIME, $metadata->fields['publishedAt']->type);
        self::assertSame(['tags', 'audio'], $metadata->facets());
        self::assertSame(['typesense' => ['default_sorting_field' => 'publishedAt']], $metadata->options);
        self::assertNull(AttributeLoader::load(Tag::class));
    }

    public function testMetadataIsPlainValues(): void
    {
        $metadata = AttributeLoader::load(Article::class);

        self::assertEquals($metadata, ClassMetadata::fromArray(unserialize(serialize($metadata->toArray()))));
    }

    public function testADirectoryIsScanned(): void
    {
        self::assertSame(['article', 'note'], array_keys(AttributeLoader::scan([__DIR__.'/Fixtures', '/nowhere'])));
    }

    public function testASubClassBelongsToItsParentsIndex(): void
    {
        $registry = MetadataRegistry::fromDirectories([__DIR__.'/Fixtures']);

        self::assertSame('note', $registry->get(new Draft('1', 'x'))->index);
        self::assertSame('note', $registry->get(Draft::class)->index);
        self::assertSame('article', $registry->get('article')->index);
        self::assertNull($registry->find(Tag::class));
        $this->expectException(MappingException::class);
        $registry->get('nothing');
    }

    public function testAnUnknownTypeIsRefused(): void
    {
        $this->expectExceptionMessage('has the type "int32"');
        AttributeLoader::load(WrongType::class);
    }

    public function testAFieldOnTheClassNeedsAName(): void
    {
        $this->expectExceptionMessage('needs a name');
        AttributeLoader::load(Unnamed::class);
    }
}

#[Index('wrong')]
#[Field('rank', type: 'int32')]
final class WrongType
{
}

#[Index('unnamed')]
#[Field(type: Type::STRING)]
final class Unnamed
{
}
