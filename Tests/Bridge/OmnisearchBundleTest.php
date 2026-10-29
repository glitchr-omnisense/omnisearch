<?php

namespace Omnisearch\Tests\Bridge;

use Omnisearch\Bridge\Symfony\IndexErrorLogger;
use Omnisearch\Bridge\Symfony\OmnisearchBundle;
use Omnisearch\Exception\DriverException;
use Omnisearch\ORM\Indexer;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\ORM\Finder;
use Omnisearch\ORM\Manager;
use Omnisearch\ORM\Query;
use Omnisearch\Registry;
use Omnisearch\Tests\Fixtures\Note;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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

    /**
     * Under the bundle, a write an engine refuses or cannot reach during Doctrine's flush does not
     * stop the application's flush (the Indexer's rule): it is logged by default; "throw" keeps the
     * exception, a service id handles it.
     */
    public function testAFailedWriteIsHandledNotThrownOutOfTheFlush(): void
    {
        self::assertEquals(new Reference(IndexErrorLogger::class), self::indexer([])->getArgument(1), 'logged by default');
        self::assertNull(self::indexer(['on_index_error' => 'throw'])->getArgument(1));
        self::assertEquals(new Reference('app.index_errors'), self::indexer(['on_index_error' => 'app.index_errors'])->getArgument(1));

        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $logs = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message, $context];
            }
        };
        $failure = new DriverException('Typesense: not reached', 503);
        (new IndexErrorLogger($logger))($failure);
        self::assertSame('error', $logger->logs[0][0]);
        self::assertStringContainsString('Typesense: not reached', $logger->logs[0][1]);
        self::assertSame($failure, $logger->logs[0][2]['exception']);
    }

    /** @param array<string, mixed> $config */
    private static function indexer(array $config): \Symfony\Component\DependencyInjection\Definition
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', \dirname(__DIR__));
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'Doctrine\\Bundle\\DoctrineBundle\\DoctrineBundle']);
        $container->registerExtension((new OmnisearchBundle())->getContainerExtension());
        $container->loadFromExtension('omnisearch', $config + ['connections' => ['default' => ['driver' => 'memory']], 'mappings' => ['%kernel.project_dir%/Fixtures']]);
        foreach ($container->getExtensions() as $extension) {
            $extension->load($container->getExtensionConfig($extension->getAlias()), $container);
        }

        return $container->getDefinition(Indexer::class);
    }
}

final class Notes
{
    public function __construct(public readonly Finder $note)
    {
    }
}
