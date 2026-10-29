---
title: Indexing
order: 32
---

# Indexing

Three ways to put documents in an index, from the most manual:

- `$manager->index(...$objects)`: these objects indexed now, each in its class's index - an
  import, a command that rebuilds an index;
- `Omnisearch\ORM\Indexer`: the rule a persistence's events call, sent once at the end;
- `Omnisearch\Bridge\Doctrine\IndexListener`: the rule on Doctrine's events (registered by the
  Symfony bundle, or by hand).

`$manager->create()` makes every index where it is not, `$manager->drop()` removes them all. To
make one index again (after a field became `infix`, for instance), drop and create it on its
connection, then index its objects again:

```php
$metadata = $manager->classMetadata(Article::class);
$connection = $manager->connection($metadata);
$connection->drop($metadata);
$connection->create($metadata);
$manager->index(...$entityManager->getRepository(Article::class)->findAll());
```

## The rule

`Indexer` is independent of any persistence's events:

| Call | When | What it does |
|---|---|---|
| `persisted($object)` | after an object is saved the first time | queues it to be indexed, when its class declares an index |
| `updated($object)` | after a change is saved | the same: a write replaces the document |
| `removing($object)` | before the object is deleted | takes its identifier from the manager's store, while it still has one |
| `removed($object)` | after it is deleted | queues the removal of that identifier |
| `flush()` | once the persistence has committed | sends everything as one `Batch`; returns the writes sent |
| `clear()` | after a rollback | forgets what is queued |

An object whose class declares no index (nor a parent of it) is ignored. The `Batch` sends the
writes in their order, consecutive writes of one index in one call, so a removal queued after an
indexing of the same document wins.

## Failures

`new Indexer($manager, $onError)`: a write an engine refuses, or that cannot reach it, goes to
`$onError(\Throwable $e)` and the next writes are still sent; the application's own flush is not
stopped. Without `$onError`, the exception is thrown out of `flush()` (out of Doctrine's
`flush()` when the listener calls it, after the database committed), and the writes after it are
forgotten.

```php
$indexer = new Indexer($manager, function (\Throwable $e) use ($logger): void {
    $logger->error('Search index not updated', ['exception' => $e]);
});
```

The Symfony bundle gives its `Indexer` an `$onError` that logs, by its `on_index_error` option
([Symfony](symfony.md#indexing-on-doctrines-events)); `throw` keeps the exception.

`removing()` takes the identifier from the manager's store, or, on a `Manager` built without one,
as the memory engine takes it (`Memory\Identity`: `getId()`, else a public `$id`).

## Some connections only

`new Indexer($manager, $onError, ['default'])`: only the objects whose index lives on one of these
connections are indexed. The Symfony bundle uses it to leave alone the connections an engine's own
bundle indexes (glitchr/typesense-bundle's listener for a hosted `typesense` connection).

## On Doctrine's events

`IndexListener::EVENTS` are `postPersist`, `postUpdate`, `preRemove`, `postRemove` and
`postFlush`; each calls the `Indexer` method of the table above, `postFlush` its `flush()`. A whole
script, on SQLite in memory and the memory engine, with `DoctrineObjectStore` loading the entities
back:

```php
// orm/Entity/Article.php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Omnisearch\Mapping\{Field, Index, Type};

#[ORM\Entity]
#[Index('article')]
class Article
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column] #[Field] private string $title,
        #[ORM\Column(type: 'json')] #[Field(type: Type::STRINGS, facet: true)] private array $tags = [],
    ) {
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getTags(): array { return $this->tags; }
}
```

```php
<?php // indexing.php

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/orm/Entity/Article.php';

use App\Entity\Article;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\{EntityManager, ORMSetup};
use Doctrine\ORM\Tools\SchemaTool;
use Omnisearch\Bridge\Doctrine\{DoctrineObjectStore, IndexListener};
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\Memory\MemoryDriverFactory;
use Omnisearch\ORM\{Indexer, Manager, Query};
use Omnisearch\Registry;

$config = ORMSetup::createAttributeMetadataConfig([__DIR__.'/orm/Entity'], true);
$config->enableNativeLazyObjects(true);
$entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
(new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

$store = new DoctrineObjectStore($entityManager);
$manager = new Manager(new Registry([new MemoryDriverFactory($store)], ['default' => ['driver' => 'memory']]), MetadataRegistry::fromDirectories([__DIR__.'/orm/Entity']), $store);
$manager->create();
$errors = [];
$entityManager->getEventManager()->addEventListener(IndexListener::EVENTS, new IndexListener(new Indexer($manager, function (\Throwable $e) use (&$errors) { $errors[] = $e->getMessage(); })));

$finder = $manager->finder(Article::class);
$titles = fn (string $term) => implode(', ', array_map(fn (Article $a) => $a->getId().' '.$a->getTitle(), $finder->search((new Query($term))->in('title'))->objects())) ?: '-';

$fuji = new Article('Mount Fuji', ['travel']);
$entityManager->persist($fuji);
$entityManager->persist(new Article('Fujisan in music', ['music']));
$entityManager->flush();                                 // postPersist x2, then postFlush sends them
echo 'after persist:  ', $titles('fuji'), "\n";

$fuji->setTitle('Kyoto gardens');
$entityManager->flush();                                 // postUpdate
echo 'after update:   ', $titles('fuji'), ' | kyoto: ', $titles('kyoto'), "\n";

$entityManager->remove($fuji);
$entityManager->flush();                                 // preRemove takes id 1, postRemove removes it
echo 'after remove:   ', $titles('kyoto'), "\n";
echo 'errors: ', count($errors), "\n";
```

```
$ php indexing.php
after persist:  1 Mount Fuji, 2 Fujisan in music
after update:   2 Fujisan in music | kyoto: 1 Kyoto gardens
after remove:   -
errors: 0
```

(run on 2026-10-26, PHP 8.4.27, Doctrine ORM 3 with native lazy objects, no network)

The entity implements nothing of omnisearch; its private properties are read through their getters.
An identifier generated by the database is there at `postPersist`, so the document's id is the row's.

## Flushes and transactions

Each Doctrine flush ends with `postFlush`, which sends what that flush queued. It runs at the end of
the flush, not at the commit of an outer transaction: inside `$entityManager->wrapInTransaction()`,
a flush has already sent its writes when the transaction is rolled back. A flush that fails before
`postFlush` leaves what it queued in the `Indexer`, sent by the next successful flush; call its
`clear()` after such a failure to forget them.
