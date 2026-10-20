<?php

namespace Omnisearch\Bridge\Symfony;

use Omnisearch\Bridge\Doctrine\DoctrineObjectStore;
use Omnisearch\Bridge\Doctrine\IndexListener;
use Omnisearch\Bridge\Symfony\Command\IndexesCommand;
use Omnisearch\Bridge\Symfony\Command\SchemaCommand;
use Omnisearch\DBAL\DriverFactory;
use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\Memory\MemoryDriverFactory;
use Omnisearch\ORM\Finder;
use Omnisearch\ORM\Indexer;
use Omnisearch\ORM\Manager;
use Omnisearch\ORM\ObjectStore;
use Omnisearch\Registry;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnisearch in a Symfony application:
 *
 *     omnisearch:
 *         connections:
 *             default: { driver: typesense, options: { connection: default } }
 *         mappings: ['%kernel.project_dir%/src/Entity']     # the classes declaring an #[Index]; src/ by default
 *
 *     public function __construct(Manager $search, Finder $blogArticle) {}
 *
 * The drivers installed are registered (omnisearch/typesense, the memory
 * one), autowired. The indexes are read from the attributes when the
 * container compiles and kept in it. An engine with a Symfony bundle of its
 * own (Host) is handed the indexes of its connections before: that bundle
 * creates and indexes them; omnisearch's Doctrine listener indexes the
 * other connections.
 */
final class OmnisearchBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnisearch';

    /** The drivers this bundle knows, registered when installed. */
    private const FACTORIES = ['Omnisearch\Typesense\TypesenseDriverFactory', MemoryDriverFactory::class];

    /** The engines whose own bundle hosts their indexes. */
    private const HOSTS = ['typesense' => 'Omnisearch\Typesense\Bridge\Symfony\TypesenseHost'];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_connection')->defaultNull()->info('The first connection when null.')->end()
                ->arrayNode('connections')
                    ->info('The connections, by name: a driver (typesense, memory...) and its options.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('driver')->isRequired()->cannotBeEmpty()->end()
                            ->variableNode('options')->defaultValue([])->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mappings')
                    ->info('The directories whose classes declare an #[Index]: src/ when none is given.')
                    ->scalarPrototype()->end()
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = (new Processor())->processConfiguration($this->getContainerExtension()->getConfiguration([], $builder), $builder->getExtensionConfig($this->extensionAlias));
        $metadata = self::metadata($config, $builder);

        $hosted = [];
        $default = $config['default_connection'] ?? array_key_first($config['connections']);
        foreach ($config['connections'] as $name => $connection) {
            $host = self::HOSTS[$connection['driver']] ?? null;
            if (null === $host || !class_exists($host) || !is_subclass_of($host, Host::class) || !$host::hosts($builder)) {
                continue;
            }
            $host::prepend($builder, $name, $connection['options'], array_values(array_filter($metadata, fn (ClassMetadata $m) => ($m->connection ?? $default) === $name)));
            $hosted[] = $name;
        }
        $builder->setParameter('omnisearch.hosted_connections', $hosted);
    }

    /**
     * @param array{default_connection: ?string, connections: array<string, array{driver: string, options: array<string, mixed>}>, mappings: list<string>} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(DriverFactory::class)->addTag('omnisearch.driver_factory');
        $metadata = self::metadata($config, $builder);
        $hosted = $builder->hasParameter('omnisearch.hosted_connections') ? $builder->getParameter('omnisearch.hosted_connections') : [];
        $doctrine = $builder->hasParameter('kernel.bundles') && isset($builder->getParameter('kernel.bundles')['DoctrineBundle']);

        $services = $container->services();
        foreach (self::FACTORIES as $factory) {
            if (class_exists($factory) && is_subclass_of($factory, DriverFactory::class)) {
                $services->set($factory)->autowire()->tag('omnisearch.driver_factory');
            }
        }

        $services->set(MetadataRegistry::class)
            ->factory([MetadataRegistry::class, 'fromArray'])
            ->args([array_values(array_map(fn (ClassMetadata $m) => $m->toArray(), $metadata))])
            ->public();

        $services->set(Registry::class)
            ->args([tagged_iterator('omnisearch.driver_factory'), $config['connections'], $config['default_connection']])
            ->public();

        if ($doctrine) {
            $services->set(DoctrineObjectStore::class)->args([service('doctrine')]);
            $services->alias(ObjectStore::class, DoctrineObjectStore::class);
        }

        $services->set(Manager::class)
            ->args([service(Registry::class), service(MetadataRegistry::class), $doctrine ? service(ObjectStore::class) : null])
            ->public();

        foreach ($metadata as $index => $classMetadata) {
            $id = 'omnisearch.finder.'.$index;
            $services->set($id, Finder::class)->factory([service(Manager::class), 'finder'])->args([$index])->public();
            $builder->registerAliasForArgument($id, Finder::class, $index);
        }

        $indexed = array_values(array_diff(array_keys($config['connections']), $hosted));
        if ($doctrine && $indexed) {
            $services->set(Indexer::class)->args([service(Manager::class), null, $indexed]);
            $listener = $services->set(IndexListener::class)->args([service(Indexer::class)]);
            foreach (IndexListener::EVENTS as $event) {
                $listener->tag('doctrine.event_listener', ['event' => $event]);
            }
        }

        if (class_exists(\Symfony\Component\Console\Command\Command::class)) {
            $services->set(IndexesCommand::class)->args([service(Manager::class)])->tag('console.command');
            $services->set(SchemaCommand::class)->args([service(Manager::class)])->tag('console.command');
        }
    }

    /**
     * @param array{mappings?: list<string>} $config
     *
     * @return array<string, ClassMetadata>
     */
    private static function metadata(array $config, ContainerBuilder $builder): array
    {
        $directories = $config['mappings'] ?? [];
        if (!$directories) {
            $directories = $builder->hasParameter('kernel.project_dir') ? ['%kernel.project_dir%/src'] : [];
        }
        $directories = (array) $builder->getParameterBag()->resolveValue($directories);
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                $builder->addResource(new DirectoryResource($directory, '/\.php$/'));
            }
        }

        return AttributeLoader::scan($directories);
    }
}
