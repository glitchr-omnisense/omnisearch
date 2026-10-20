<?php

namespace Omnisearch\Mapping;

use Omnisearch\Exception\MappingException;

/**
 * The application's indexes: by index name, and by class - an object of a
 * sub-class belongs to its nearest declared parent's index.
 */
final class MetadataRegistry
{
    /** @var array<string, ClassMetadata> */
    private array $byIndex = [];

    /** @var array<class-string, ClassMetadata> */
    private array $byClass = [];

    /** @param iterable<ClassMetadata> $metadata */
    public function __construct(iterable $metadata = [])
    {
        foreach ($metadata as $classMetadata) {
            $this->add($classMetadata);
        }
    }

    /** @param list<string> $directories */
    public static function fromDirectories(array $directories): self
    {
        return new self(AttributeLoader::scan($directories));
    }

    /** @param list<array<string, mixed>> $metadata ClassMetadata::toArray()s, as a container keeps them */
    public static function fromArray(array $metadata): self
    {
        return new self(array_map(ClassMetadata::fromArray(...), $metadata));
    }

    public function add(ClassMetadata $metadata): self
    {
        $this->byIndex[$metadata->index] = $metadata;
        $this->byClass[$metadata->class] = $metadata;

        return $this;
    }

    /** @return array<string, ClassMetadata> */
    public function all(): array
    {
        return $this->byIndex;
    }

    public function has(string $indexOrClass): bool
    {
        return null !== $this->find($indexOrClass);
    }

    /** The metadata of an index by its name, or of a class (or of its nearest declared parent). */
    public function find(string|object $indexOrClass): ?ClassMetadata
    {
        if (\is_string($indexOrClass) && isset($this->byIndex[$indexOrClass])) {
            return $this->byIndex[$indexOrClass];
        }
        $class = \is_object($indexOrClass) ? $indexOrClass::class : $indexOrClass;
        for (; $class; $class = get_parent_class($class)) {
            if (isset($this->byClass[$class])) {
                return $this->byClass[$class];
            }
            if (!class_exists($class)) {
                break;
            }
        }

        return null;
    }

    public function get(string|object $indexOrClass): ClassMetadata
    {
        return $this->find($indexOrClass) ?? throw new MappingException(\sprintf('No index for "%s"; declared: %s.', \is_object($indexOrClass) ? $indexOrClass::class : $indexOrClass, implode(', ', array_keys($this->byIndex)) ?: 'none'));
    }
}
