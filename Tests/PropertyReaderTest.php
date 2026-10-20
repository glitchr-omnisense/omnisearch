<?php

namespace Omnisearch\Tests;

use Omnisearch\ORM\PropertyReader;
use Omnisearch\Tests\Fixtures\Article;
use Omnisearch\Tests\Fixtures\Note;
use Omnisearch\Tests\Fixtures\Tag;
use PHPUnit\Framework\TestCase;

final class PropertyReaderTest extends TestCase
{
    public function testPathsGettersListsAndStrings(): void
    {
        $article = new Article('Fuji in music', new \DateTimeImmutable('@1700000000'), new Tag('Travels', 'voyages'), new Tag('Music', 'en-musique'));

        self::assertSame('Fuji in music', PropertyReader::read($article, 'title'));
        self::assertSame(['voyages', 'en-musique'], PropertyReader::read($article, 'tags.slug'), 'through a Doctrine collection');
        self::assertSame(['Travels', 'Music'], PropertyReader::read($article, 'tags'), 'objects by their __toString()');
        self::assertTrue(PropertyReader::read($article, 'isAudible'), 'a method by its name');
        self::assertSame(['Fuji in music'], PropertyReader::read($article, 'title', true), 'a single value of a list field, wrapped');
        self::assertSame(['a', 'b'], PropertyReader::read(new Note('1', 't', 0, ['a', 'b']), 'labels'), 'a public property');
        self::assertNull(PropertyReader::read($article, 'nothing'));
    }
}
