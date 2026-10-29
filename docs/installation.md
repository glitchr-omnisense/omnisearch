---
title: Installation
order: 10
---

# Installation

```sh
composer require omnisense/omnisearch                        # the core and its memory engine
composer require omnisense/omnisearch omnisearch/typesense   # with Typesense
```

## Requirements

- PHP 8.2 or later. `omnisense/omnisearch` requires nothing else.
- Suggested, loaded only by the bridge that needs them: `doctrine/orm` (^3.0) for
  `Omnisearch\Bridge\Doctrine`; `symfony/config`, `symfony/dependency-injection`,
  `symfony/http-kernel` (^7.0 or ^8.0) for the bundle, `symfony/console` for its commands;
  `phpunit/phpunit` (^11.0) for the conformance suite.
- `omnisearch/typesense` requires `glitchr/typesense-bundle` (^2.0), whose framework-free part
  it uses; the bundle requires `doctrine/orm`, `typesense/typesense-php` (^4.0 or ^5.0),
  `php-http/curl-client` and `symfony/cache`. It indexes Doctrine entities: it needs an
  `ObjectManager` ([omnisearch/typesense](https://github.com/glitchr-omnisense/omnisearch-typesense/blob/1.x/docs/index.md)).

## Plain PHP

A whole script on the memory engine: no server, no framework, no Doctrine. The application's own
`ObjectStore` says what identifies a note and loads notes back from identifiers, so the search
answers notes rather than documents.

```php
<?php // notes.php

require __DIR__.'/vendor/autoload.php';

use Omnisearch\Mapping\{ClassMetadata, Field, Index, MetadataRegistry, Type};
use Omnisearch\Memory\MemoryDriverFactory;
use Omnisearch\ORM\{Indexer, Manager, ObjectStore, Query};
use Omnisearch\Registry;

#[Index('note')]
#[Field('labels', type: Type::STRINGS, facet: true)]
final class Note
{
    public function __construct(
        public readonly string $id,
        #[Field(infix: true)] public readonly string $text,
        #[Field(type: Type::INT, sort: true)] public readonly int $rank,
        public readonly array $labels = [],
    ) {
    }
}

// Where the notes live: what identifies one, and the notes of identifiers.
final class Notes implements ObjectStore
{
    /** @var array<string, Note> */
    public array $notes = [];

    public function identify(object $object): string
    {
        return $object->id;
    }

    public function load(ClassMetadata $metadata, array $ids, bool $cacheable = false): array
    {
        return array_values(array_filter(array_map(fn ($id) => $this->notes[$id] ?? null, $ids)));
    }
}

$store = new Notes();
$manager = new Manager(
    new Registry([new MemoryDriverFactory($store)], ['default' => ['driver' => 'memory']]),
    new MetadataRegistry([Omnisearch\Mapping\AttributeLoader::load(Note::class)]),
    $store,
);
$manager->create();                                       // every index, where it is not

$indexer = new Indexer($manager);                         // the rule: persisted, updated, removed
foreach ([new Note('1', 'Mount Fuji at dawn', 3, ['travel', 'japan']), new Note('2', 'Fujisan in music', 1, ['music', 'japan']), new Note('3', 'A recipe for ramen', 2, ['food'])] as $note) {
    $store->notes[$note->id] = $note;
    $indexer->persisted($note);
}
echo $indexer->flush(), " writes sent\n";

$result = $manager->finder(Note::class)->search((new Query('fuji'))->in('text')->facet('labels')->sortBy('rank', 'desc'));
echo $result->found(), " found\n";
foreach ($result->objects() as $note) {
    printf("%s  %-20s matched %s\n", $note->id, $note->text, implode(', ', $result->hit($note)->matchedTokens()));
}
foreach ($result->facets(checked: ['labels' => ['japan']])['labels']['counts'] as $value => $count) {
    printf("  %-7s %d%s\n", $value, $count['count'], $count['checked'] ? ' (checked)' : '');
}

$indexer->removing($store->notes['2']);                   // before it goes: its identifier
$indexer->removed($store->notes['2']);
unset($store->notes['2']);
$indexer->flush();
echo implode(', ', $manager->finder('note')->raw((new Query('fuji'))->in('text'))->ids()), " left\n";

$manager->drop();
$gone = $manager->finder('note')->search(new Query('fuji'));
echo $gone->status(), ' ', $gone->error(), "\n";
```

```
$ php notes.php
3 writes sent
2 found
1  Mount Fuji at dawn   matched Fuji
2  Fujisan in music     matched Fujisan
  japan   2 (checked)
  travel  1
  music   1
1 left
404 No index "note".
```

(run on 2026-10-26 with PHP 8.4.27, the core's checkout on the autoloader, no network)

The pieces:

- the **registry** (`Omnisearch\Registry`): driver factories, and the connections by name, each
  `{driver, options}`; the first is the default;
- the **metadata** (`MetadataRegistry`): what the attributes declare, read once by
  `AttributeLoader::load()` or `MetadataRegistry::fromDirectories([...])`;
- the **manager** (`Omnisearch\ORM\Manager`): `create()` and `drop()` the indexes, `index()`
  objects now, `finder()` per index;
- the **indexer** (`Omnisearch\ORM\Indexer`): the rule a persistence's events call -
  `persisted()`, `updated()`, `removing()` then `removed()`, sent on `flush()`;
- the **store** (`Omnisearch\ORM\ObjectStore`): `identify()` and `load()`. Without one, the
  memory engine identifies an object by its `getId()` or its public `$id` (`Memory\Identity`),
  `Result::objects()` is empty and only the documents come back (`raw()`, `hits()`).

Without a store, the `Indexer` takes a removed object's identifier the same way, so it is taken
out of its index.

## Doctrine and Typesense

The same in an application with Doctrine and a Typesense server: `DoctrineObjectStore` identifies
entities by their identifier and loads them back in one query, `IndexListener` runs the rule on
Doctrine's events.

```php
use Omnisearch\Bridge\Doctrine\DoctrineObjectStore;
use Omnisearch\Bridge\Doctrine\IndexListener;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\ORM\Indexer;
use Omnisearch\ORM\Manager;
use Omnisearch\ORM\Query;
use Omnisearch\Registry;
use Omnisearch\Typesense\TypesenseDriverFactory;

// $entityManager: the application's Doctrine EntityManager
$registry = new Registry([new TypesenseDriverFactory(null, $entityManager)], [
    'default' => ['driver' => 'typesense', 'options' => ['url' => 'http://localhost:8108', 'secret' => 'xyz']],
]);
$manager = new Manager($registry, MetadataRegistry::fromDirectories([__DIR__.'/src/Entity']), new DoctrineObjectStore($entityManager));

$manager->create();                                     // every index, where it is not
$entityManager->getEventManager()->addEventListener(
    IndexListener::EVENTS,
    new IndexListener(new Indexer($manager, fn (\Throwable $e) => error_log($e->getMessage()))),
);

$entityManager->persist($article);
$entityManager->flush();                                // indexed on postFlush

$articles = $manager->finder(Article::class)->search((new Query('fuji'))->in('title'))->objects();
```

The `Indexer`'s second argument receives what an engine refuses during the flush; without it the
failure is thrown out of `flush()`, after the database has committed ([Indexing](indexing.md)).
`docker/harness/bin/bare` is a whole script of both halves, run by `docker compose run --rm
omnisearch bare` ([harness](harness.md)); the Typesense half was not run for this page (no
Typesense server at hand), the [verified](verified.md) page says where it was.

## In a framework

- **Symfony**: `Omnisearch\Bridge\Symfony\OmnisearchBundle` reads the attributes when the
  container compiles, registers the drivers installed, a finder per index, the Doctrine listener,
  and hands Typesense's indexes to glitchr/typesense-bundle: [Symfony](symfony.md).
- **Laravel**: no bridge exists; a service provider builds the manager once: [Laravel](laravel.md).
- **Any other**: build the `Manager` once, where the framework builds its services, as the
  scripts above do.
