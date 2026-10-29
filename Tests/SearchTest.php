<?php

namespace Omnisearch\Tests;

use Omnisearch\DBAL\Batch;
use Omnisearch\DBAL\Connection;
use Omnisearch\DBAL\Driver;
use Omnisearch\DBAL\Request;
use Omnisearch\DBAL\Response;
use Omnisearch\Exception\DriverException;
use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\DBAL\Filter;
use Omnisearch\Exception\InvalidConfigException;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\Memory\MemoryDriverFactory;
use Omnisearch\ORM\Indexer;
use Omnisearch\ORM\Manager;
use Omnisearch\ORM\Query;
use Omnisearch\Registry;
use Omnisearch\Tests\Fixtures\ArrayStore;
use Omnisearch\Tests\Fixtures\Draft;
use Omnisearch\Tests\Fixtures\Note;
use PHPUnit\Framework\TestCase;

/**
 * The whole layer on the memory engine: the manager, its finders, the
 * indexing rule, a batch, results and facets.
 */
final class SearchTest extends TestCase
{
    private ArrayStore $store;
    private Manager $manager;

    protected function setUp(): void
    {
        $this->store = new ArrayStore();
        $registry = new Registry([new MemoryDriverFactory($this->store)], ['default' => ['driver' => 'memory']]);
        $this->manager = new Manager($registry, MetadataRegistry::fromDirectories([__DIR__.'/Fixtures']), $this->store);
        $this->manager->create();

        $notes = [
            new Note('1', 'Mount Fuji at dawn', 3, ['travel', 'japan']),
            new Note('2', 'Fujisan in music', 1, ['music', 'japan']),
            new Draft('3', 'A recipe for ramen', 2, ['food']),
        ];
        $this->store->add(...$notes);
        $this->manager->index(...$notes);
    }

    public function testObjectsComeBackInTheOrderOfTheHits(): void
    {
        $result = $this->manager->finder(Note::class)->search((new Query('fuji'))->in('text')->sortBy('rank'));

        self::assertSame(2, $result->found());
        self::assertSame(['2', '1'], array_map(fn (Note $n) => $n->id, $result->objects()));
        self::assertSame(['Fujisan'], $result->hit($result->objects()[0])->matchedTokens(), 'the words it matched');
        self::assertSame(1, $result->totalPages(10));
    }

    public function testFiltersFacetsAndPages(): void
    {
        $finder = $this->manager->finder('note');

        $result = $finder->search((new Query())->where('labels', 'japan')->facet('labels')->sortBy('rank', 'desc')->perPage(1)->page(2));
        self::assertSame(2, $result->found());
        self::assertSame(['2'], array_map(fn (Note $n) => $n->id, $result->objects()), 'page two of one');
        $facets = $result->facets(['labels' => ['music']], true);
        self::assertSame(['japan', 'music', 'travel'], array_keys($facets['labels']['counts']), 'sorted by value');
        self::assertSame(['value' => 'japan', 'count' => 2, 'checked' => false], $facets['labels']['counts']['japan']);
        self::assertTrue($facets['labels']['counts']['music']['checked']);

        self::assertSame(['3'], $finder->raw((new Query())->filter(Filter::notIn('labels', ['japan'])))->ids());
        self::assertSame(['1'], $finder->raw((new Query())->filter(new Filter('rank', Filter::GREATER, 2)))->ids());
        self::assertSame(2, $finder->facets(['labels'])['labels']['counts']['japan']['count'], 'among all the documents');
    }

    public function testTheIndexingRule(): void
    {
        $indexer = new Indexer($this->manager);
        $note = new Note('4', 'Kyoto gardens', 5, ['travel']);
        $this->store->add($note);

        $indexer->persisted($note);
        $indexer->persisted(new \stdClass());
        self::assertSame(0, $this->manager->finder('note')->raw(new Query('kyoto'))->found, 'nothing sent before the flush');
        self::assertSame(1, $indexer->flush());
        self::assertSame(['4'], $this->manager->finder('note')->raw(new Query('kyoto'))->ids());

        $indexer->removing($note);
        $indexer->removed($note);
        $indexer->flush();
        self::assertSame(0, $this->manager->finder('note')->raw(new Query('kyoto'))->found);
    }

    /** On a manager with no store (the memory engine's installation), a removed object is still taken out of its index. */
    public function testTheIndexingRuleWithoutAStore(): void
    {
        $manager = new Manager(new Registry([new MemoryDriverFactory()], ['default' => ['driver' => 'memory']]), MetadataRegistry::fromDirectories([__DIR__.'/Fixtures']));
        $manager->create();
        $indexer = new Indexer($manager);
        $note = new Note('8', 'Nara deer');

        $indexer->persisted($note);
        $indexer->flush();
        self::assertSame(['8'], $manager->finder('note')->raw(new Query('nara'))->ids());

        $indexer->removing($note);
        $indexer->removed($note);
        self::assertSame(1, $indexer->flush(), 'the removal sent');
        self::assertSame(0, $manager->finder('note')->raw(new Query('nara'))->found);
    }

    public function testAnIndexerLimitedToOtherConnectionsLeavesThisOneAlone(): void
    {
        $indexer = new Indexer($this->manager, null, ['elsewhere']);

        self::assertFalse($indexer->supports(new Note('5', 'x')));
    }

    public function testABatchSendsConsecutiveWritesTogetherAndAFailureToOnError(): void
    {
        $metadata = $this->manager->classMetadata('note');
        $connection = $this->manager->connection($metadata);
        $batch = (new Batch())->index($connection, $metadata, new Note('6', 'Osaka'))->index($connection, $metadata, new Note('7', 'Osaka castle'))->remove($connection, $metadata, '6');

        self::assertSame(3, $batch->commit());
        self::assertSame(['7'], $this->manager->finder('note')->raw(new Query('osaka'))->ids(), 'the removal after the indexing wins');

        $errors = [];
        $broken = new Connection('broken', new class implements Driver {
            public function create(ClassMetadata $metadata): void {}
            public function drop(ClassMetadata $metadata): void {}
            public function schema(ClassMetadata $metadata): array { return []; }
            public function index(ClassMetadata $metadata, object ...$objects): void { throw new DriverException('Not Ready or Lagging', 503); }
            public function remove(ClassMetadata $metadata, string ...$ids): void {}
            public function search(ClassMetadata $metadata, Request $request): Response { return new Response(); }
        });
        $sent = (new Batch())->index($broken, $metadata, new Note('8', 'x'))->index($connection, $metadata, new Note('9', 'Nara'))->commit(function (\Throwable $e) use (&$errors) { $errors[] = $e->getCode(); });
        self::assertSame(1, $sent, 'what could be sent was');
        self::assertSame([503], $errors);
    }

    public function testAMissingConnectionOrDriverIsSaid(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('No "elastic" driver');
        (new Registry([], ['default' => ['driver' => 'elastic']]))->connection();
    }

    public function testAnIndexNotCreatedAnswersAStatus(): void
    {
        $this->manager->drop();
        $result = $this->manager->finder('note')->search(new Query('fuji'));

        self::assertFalse($result->ok());
        self::assertSame(404, $result->status());
    }
}
