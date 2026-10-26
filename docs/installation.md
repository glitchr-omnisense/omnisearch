---
title: Installation
order: 1
---

# Installation, in plain PHP

```sh
composer require omnisense/omnisearch omnisearch/typesense   # or the memory engine alone: omnisense/omnisearch
```

The whole thing by hand - no framework, no container:

```php
use Omnisearch\Bridge\Doctrine\DoctrineObjectStore;
use Omnisearch\Bridge\Doctrine\IndexListener;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\ORM\Indexer;
use Omnisearch\ORM\Manager;
use Omnisearch\ORM\Query;
use Omnisearch\Registry;
use Omnisearch\Typesense\TypesenseDriverFactory;

// $entityManager: your Doctrine EntityManager
$store = new DoctrineObjectStore($entityManager);
$registry = new Registry([new TypesenseDriverFactory(null, $entityManager)], [
    'default' => ['driver' => 'typesense', 'options' => ['url' => 'http://localhost:8108', 'secret' => 'xyz']],
]);
$manager = new Manager($registry, MetadataRegistry::fromDirectories([__DIR__.'/src/Entity']), $store);

$manager->create();                                                        // every index, where it is not
$entityManager->getEventManager()->addEventListener(IndexListener::EVENTS, new IndexListener(new Indexer($manager)));

$entityManager->persist($article);
$entityManager->flush();                                                   // indexed

$articles = $manager->finder(Article::class)->search((new Query('fuji'))->in('title'))->objects();
```

Without Doctrine, an `ObjectStore` of your own says what identifies an object and loads objects
back from identifiers; with the memory engine and no store at all, an object is identified by its
`getId()` or its public `$id`:

```php
use Omnisearch\Memory\MemoryDriverFactory;

$registry = new Registry([new MemoryDriverFactory()], ['default' => ['driver' => 'memory']]);
$manager = new Manager($registry, MetadataRegistry::fromDirectories([__DIR__.'/src']));
$manager->create();
$manager->index(...$notes);
$manager->finder(Note::class)->raw((new Query('fuji'))->in('text'))->ids();
```

`docker/harness/bin/bare` is a whole script of both.

Next: [mapping](mapping.md), [searching](searching.md), [Symfony](symfony.md).
