<?php

namespace Omnisearch\Tests\Bridge;

use Omnisearch\Bridge\Symfony\OmnisearchBundle;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\ORM\Finder;
use Omnisearch\ORM\Manager;
use Omnisearch\ORM\Query;
use Omnisearch\Registry;
use Omnisearch\Tests\Fixtures\Note;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class OmnisearchBundleTest extends TestCase
{
    public function testTheIndexesOfTheAttributesAndAFinderByName(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', \dirname(__DIR__));
        $container->setParameter('kernel.bundles', []);
        $container->register(Notes::class)->setAutowired(true)->setPublic(true);
        $bundle = new OmnisearchBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('omnisearch', [
            'connections' => ['default' => ['driver' => 'memory']],
            'mappings' => ['%kernel.project_dir%/Fixtures'],
        ]);
        $container->compile();

        self::assertSame(['article', 'note'], array_keys($container->get(MetadataRegistry::class)->all()), 'read when the container compiled');
        self::assertSame(['memory'], [$container->get(Registry::class)->driverName('default')]);
        self::assertSame([], $container->getParameter('omnisearch.hosted_connections'), 'no engine bundle hosts the memory engine');

        $manager = $container->get(Manager::class);
        $manager->create();
        $manager->index(new Note('1', 'Mount Fuji'));
        $notes = $container->get(Notes::class);
        self::assertSame('note', $notes->note->metadata->index, 'injected by its name');
        self::assertSame(['1'], $notes->note->raw(new Query('fuji'))->ids());
    }
}

final class Notes
{
    public function __construct(public readonly Finder $note)
    {
    }
}
