<?php

namespace Omnisearch\Bridge\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\ORM\ObjectStore;

/**
 * Doctrine's entities behind the documents: an entity is identified by its
 * identifier's values (joined by "-" when composite), the hits' entities
 * loaded in one query and put in the order of the hits - on any database.
 * $cacheable makes the query cacheable in the second-level cache, in the
 * entity's region.
 */
final class DoctrineObjectStore implements ObjectStore
{
    public function __construct(private readonly ObjectManager|ManagerRegistry $doctrine)
    {
    }

    private function manager(string $class): ObjectManager
    {
        return $this->doctrine instanceof ManagerRegistry ? ($this->doctrine->getManagerForClass($class) ?? $this->doctrine->getManager()) : $this->doctrine;
    }

    public function identify(object $object): string
    {
        $values = $this->manager($object::class)->getClassMetadata($object::class)->getIdentifierValues($object);

        return implode('-', array_map(fn ($v) => \is_object($v) && method_exists($v, '__toString') ? (string) $v : (string) $v, $values));
    }

    public function load(ClassMetadata $metadata, array $ids, bool $cacheable = false): array
    {
        if (!$ids) {
            return [];
        }
        $manager = $this->manager($metadata->class);
        $classMetadata = $manager->getClassMetadata($metadata->class);
        $identifier = $classMetadata->getIdentifierFieldNames()[0];

        if ($manager instanceof EntityManagerInterface) {
            $query = $manager->createQueryBuilder()
                ->select('e')->from($metadata->class, 'e')
                ->where('e.'.$identifier.' IN (:ids)')->setParameter('ids', $ids)
                ->setCacheable($cacheable)
                ->getQuery()->useQueryCache($cacheable);
            if ($cacheable && null !== ($region = $classMetadata->cache['region'] ?? null)) {
                $query->setCacheRegion($region);
            }
            $objects = $query->getResult();
        } else {
            $objects = $manager->getRepository($metadata->class)->findBy([$identifier => $ids]);
        }

        $position = array_flip(array_map('strval', $ids));
        $objects = array_values(array_filter($objects, fn ($o) => isset($position[$this->identify($o)])));
        usort($objects, fn ($a, $b) => $position[$this->identify($a)] <=> $position[$this->identify($b)]);

        return $objects;
    }
}
