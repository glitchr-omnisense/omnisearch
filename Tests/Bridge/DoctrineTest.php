<?php

namespace Omnisearch\Tests\Bridge;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Omnisearch\Bridge\Doctrine\DoctrineObjectStore;
use Omnisearch\Bridge\Doctrine\IndexListener;
use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\Memory\MemoryDriverFactory;
use Omnisearch\ORM\Indexer;
use Omnisearch\ORM\Manager;
use Omnisearch\ORM\Query;
use Omnisearch\Registry;
use Omnisearch\Tests\Fixtures\Article;
use Omnisearch\Tests\Fixtures\Tag;
use PHPUnit\Framework\TestCase;

/**
 * Doctrine's entities behind the documents: indexed on flush by the
 * listener, loaded back from a search in the order of the hits, removed.
 */
final class DoctrineTest extends TestCase
{
    public static function entityManager(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__).'/Fixtures'], true);
        if (\PHP_VERSION_ID >= 80400 && method_exists($configuration, 'enableNativeLazyObjects')) {
            $configuration->enableNativeLazyObjects(true);
        }
        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    /** A composite identifier, joined by "-" (a value holding "-" too), identifies and loads back its entity. */
    public function testAnEntityWithACompositeIdentifierIsLoadedBack(): void
    {
        if (!class_exists(EntityManager::class)) {
            self::markTestSkipped('doctrine/orm is not installed.');
        }
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__).'/Composite'], true);
        if (\PHP_VERSION_ID >= 80400 && method_exists($configuration, 'enableNativeLazyObjects')) {
            $configuration->enableNativeLazyObjects(true);
        }
        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $store = new DoctrineObjectStore($entityManager);
        $manager = new Manager(new Registry([new MemoryDriverFactory($store)], ['default' => ['driver' => 'memory']]), MetadataRegistry::fromDirectories([\dirname(__DIR__).'/Composite']), $store);
        $manager->create();

        $editions = [new \Omnisearch\Tests\Composite\Edition('978-2-07-036822-8', 1, 'Vol de nuit'), new \Omnisearch\Tests\Composite\Edition('978-2-07-036822-8', 2, 'Vol de nuit, reprinted'), new \Omnisearch\Tests\Composite\Edition('978-2-07', 36822, 'Not the same edition')];
        foreach ($editions as $edition) {
            $entityManager->persist($edition);
        }
        $entityManager->flush();
        $manager->index(...$editions);
        $entityManager->clear();

        self::assertSame('978-2-07-036822-8-2', $store->identify($editions[1]));
        $loaded = $store->load(AttributeLoader::load(\Omnisearch\Tests\Composite\Edition::class), ['978-2-07-036822-8-2', '978-2-07-36822', '978-2-07-036822-8-1']);
        self::assertSame(['Vol de nuit, reprinted', 'Not the same edition', 'Vol de nuit'], array_map(static fn ($e) => $e->getTitle(), $loaded), 'each by its whole identifier, in the order asked');

        $result = $manager->finder(\Omnisearch\Tests\Composite\Edition::class)->search(new Query('vol'));
        self::assertCount(2, $result->objects(), 'the hits loaded back');
    }

    public function testEntitiesAreIndexedOnFlushFoundAndRemoved(): void
    {
        if (!class_exists(EntityManager::class)) {
            self::markTestSkipped('doctrine/orm is not installed.');
        }
        $entityManager = self::entityManager();
        $store = new DoctrineObjectStore($entityManager);
        $manager = new Manager(new Registry([new MemoryDriverFactory($store)], ['default' => ['driver' => 'memory']]), MetadataRegistry::fromDirectories([\dirname(__DIR__).'/Fixtures']), $store);
        $manager->create();
        $entityManager->getEventManager()->addEventListener(IndexListener::EVENTS, new IndexListener(new Indexer($manager)));

        $music = new Tag('Music', 'en-musique');
        $entityManager->persist($first = new Article('Fuji', new \DateTimeImmutable('2026-01-01'), new Tag('Travels', 'voyages')));
        $entityManager->persist(new Article('Fujisan in music', new \DateTimeImmutable('2026-03-01'), $music));
        $entityManager->flush();
        $entityManager->clear();

        $result = $manager->finder(Article::class)->search((new Query('fuji'))->in('title')->sortBy('publishedAt', 'desc')->facet('tags', 'audio'));
        self::assertSame(['Fujisan in music', 'Fuji'], array_map(fn (Article $a) => $a->getTitle(), $result->objects()), 'entities in the order of the hits, not of the table');
        self::assertSame(['en-musique', 'voyages'], array_keys($result->facets()['tags']['counts']));
        self::assertSame(1, $result->facets()['audio']['counts']['true']['count']);
        self::assertSame(['en-musique'], $result->hit($result->objects()[0])->document['tags']);
        self::assertSame((new \DateTimeImmutable('2026-03-01'))->getTimestamp(), $result->hit($result->objects()[0])->document['publishedAt']);

        $entityManager->remove($entityManager->find(Article::class, $first->getId()));
        $entityManager->flush();
        self::assertSame(1, $manager->finder('article')->raw((new Query('fuji'))->in('title'))->found);
    }
}
