<?php

namespace Omnisearch\Bridge\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\ORM\ObjectStore;

/**
 * Doctrine's entities behind the documents: an entity is identified by its
 * identifier's values (joined by "-" when composite, and split back on
 * loading - every split a value holding "-" allows, the entities whose
 * identify() is the id kept), the hits' entities loaded in one query and put in the order of the hits - on any database.
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
        $fields = $classMetadata->getIdentifierFieldNames();

        if ($manager instanceof EntityManagerInterface) {
            $builder = $manager->createQueryBuilder()->select('e')->from($metadata->class, 'e');
            if (1 === \count($fields)) {
                $builder->where('e.'.$fields[0].' IN (:ids)')->setParameter('ids', $ids);
            } else {
                // A composite identifier: each id split back into its values, every split
                // its "-" allows (a value may hold "-" too); identify() keeps the right ones.
                $or = $builder->expr()->orX();
                $n = 0;
                foreach (self::tuples($ids, \count($fields)) as $tuple) {
                    $and = $builder->expr()->andX();
                    foreach ($fields as $i => $field) {
                        $and->add('e.'.$field.' = :v'.$n.'_'.$i);
                        $builder->setParameter('v'.$n.'_'.$i, $tuple[$i]);
                    }
                    $or->add($and);
                    ++$n;
                }
                if (0 === $n) {
                    return [];
                }
                $builder->where($or);
            }
            $query = $builder->setCacheable($cacheable)->getQuery()->useQueryCache($cacheable);
            if ($cacheable && null !== ($region = $classMetadata->cache['region'] ?? null)) {
                $query->setCacheRegion($region);
            }
            $objects = $query->getResult();
        } elseif (1 === \count($fields)) {
            $objects = $manager->getRepository($metadata->class)->findBy([$fields[0] => $ids]);
        } else {
            $objects = [];
            foreach (self::tuples($ids, \count($fields)) as $tuple) {
                array_push($objects, ...$manager->getRepository($metadata->class)->findBy(array_combine($fields, $tuple)));
            }
        }

        $position = array_flip(array_map('strval', $ids));
        $objects = array_values(array_filter($objects, fn ($o) => isset($position[$this->identify($o)])));
        usort($objects, fn ($a, $b) => $position[$this->identify($a)] <=> $position[$this->identify($b)]);

        return $objects;
    }

    /**
     * The ways each id splits into $count values at its "-".
     *
     * @param list<string> $ids
     *
     * @return iterable<list<string>>
     */
    private static function tuples(array $ids, int $count): iterable
    {
        foreach ($ids as $id) {
            yield from self::splits(explode('-', (string) $id), $count);
        }
    }

    /**
     * @param list<string> $parts
     *
     * @return iterable<list<string>>
     */
    private static function splits(array $parts, int $count): iterable
    {
        if (1 === $count) {
            yield [implode('-', $parts)];

            return;
        }
        for ($i = 1; $i <= \count($parts) - $count + 1; ++$i) {
            foreach (self::splits(\array_slice($parts, $i), $count - 1) as $rest) {
                yield [implode('-', \array_slice($parts, 0, $i)), ...$rest];
            }
        }
    }
}
