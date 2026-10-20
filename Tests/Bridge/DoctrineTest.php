<?php

namespace Omnisearch\Tests\Bridge;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Omnisearch\Bridge\Doctrine\DoctrineObjectStore;
use Omnisearch\Bridge\Doctrine\IndexListener;
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
