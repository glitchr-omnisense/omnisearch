<?php

namespace Omnisearch;

use Omnisearch\DBAL\Connection;
use Omnisearch\DBAL\DriverFactory;
use Omnisearch\Exception\InvalidConfigException;

/**
 * The application's connections by name, each made once by its engine's
 * factory from its options:
 *
 *   new Registry([new TypesenseDriverFactory(), new MemoryDriverFactory()], [
 *       'default' => ['driver' => 'typesense', 'options' => ['url' => 'http://localhost:8108', 'secret' => '...']],
 *       'tests' => ['driver' => 'memory'],
 *   ]);
 *
 * The first connection is the default one unless $default names another.
 */
final class Registry
{
    /** @var array<string, DriverFactory> */
    private array $factories = [];

    /** @var array<string, Connection> */
    private array $connections = [];

    private readonly string $default;

    /**
     * @param iterable<DriverFactory>                                               $factories
     * @param array<string, array{driver: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config, ?string $default = null)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->name()] = $factory;
        }
        $this->default = $default ?? (string) array_key_first($config);
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->default;
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }
        $connection = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" connection; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));
        $factory = $this->factories[$connection['driver']] ?? throw new InvalidConfigException(\sprintf('No "%s" driver for the "%s" connection; installed: %s.', $connection['driver'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));

        return $this->connections[$name] = new Connection($name, $factory->create($connection['options'] ?? []));
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    public function defaultName(): string
    {
        return $this->default;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** The engine a connection is configured with ("typesense"...). */
    public function driverName(string $name): string
    {
        return $this->config[$name]['driver'] ?? throw new InvalidConfigException(\sprintf('No "%s" connection.', $name));
    }
}
