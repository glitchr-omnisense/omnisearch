# omnisense/omnisearch

Search the way Doctrine is for SQL, **whatever the engine**: the classes declare their index by
attributes, a manager hands out finders, a query is written once, the objects come back from the
hits. A DBAL - connections, drivers, requests and responses - under an ORM - metadata, finders,
queries, results, the indexing rule.

## Install and a first call

```sh
composer require omnisense/omnisearch
```

No server, no account, no framework: the memory engine (`Omnisearch\Memory`) runs the search in the
PHP process itself. The application's own `ObjectStore` says what identifies an object and loads
objects back from identifiers, so the search answers notes rather than documents. A real engine is
a package of its own: `omnisearch/typesense` needs a Typesense server (one's own, which needs no
account, or a Typesense Cloud cluster) and its API key.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnisearch\Mapping\{AttributeLoader, ClassMetadata, Field, Index, MetadataRegistry, Type};
use Omnisearch\Memory\MemoryDriverFactory;
use Omnisearch\ORM\{Indexer, Manager, ObjectStore, Query};
use Omnisearch\Registry;

#[Index('note')]
final class Note
{
    public function __construct(public readonly string $id, #[Field(infix: true)] public readonly string $text, #[Field(type: Type::INT, sort: true)] public readonly int $rank)
    {
    }
}

final class Notes implements ObjectStore   // where the notes live: an array here, a database in an application
{
    public array $notes = [];

    public function identify(object $object): string { return $object->id; }

    public function load(ClassMetadata $metadata, array $ids, bool $cacheable = false): array
    {
        return array_values(array_filter(array_map(fn ($id) => $this->notes[$id] ?? null, $ids)));
    }
}

$store = new Notes();
$manager = new Manager(new Registry([new MemoryDriverFactory($store)], ['default' => ['driver' => 'memory']]), new MetadataRegistry([AttributeLoader::load(Note::class)]), $store);
$manager->create();
$indexer = new Indexer($manager);
foreach ([new Note('1', 'Mount Fuji at dawn', 3), new Note('2', 'Fujisan in music', 1), new Note('3', 'A recipe for ramen', 2)] as $note) {
    $indexer->persisted($store->notes[$note->id] = $note);
}
$indexer->flush();

foreach ($manager->finder(Note::class)->search((new Query('fuji'))->in('text')->sortBy('rank', 'desc'))->objects() as $note) {
    echo $note->id, ' ', $note->text, "\n";   // 1 Mount Fuji at dawn, then 2 Fujisan in music
}
```

An application's class, its index declared by attributes, then a search:

```php
use Omnisearch\Mapping\{Field, Index, Type};

#[Index('article')]
#[Field('title', type: Type::STRINGS, property: 'translations.title')]
#[Field('tags', type: Type::STRINGS, property: 'tags.slug', facet: true)]
#[Field('publishedAt', type: Type::DATETIME, sort: true)]
class Article { /* your entity, implementing nothing of omnisearch */ }

$result = $manager->finder(Article::class)->search(
    (new Query('fuji'))->in('title')->where('tags', 'en-musique')->facet('tags')->sortBy('publishedAt', 'desc')->perPage(10)
);
$result->objects();                                 // Article entities, in the order of the hits
$result->hit($result->objects()[0])->matchedTokens();   // the words it matched
$result->facets(checked: ['tags' => ['en-musique']]);   // each tag, its count, checked or not
```

This package holds the contract and the layers above it; it requires PHP alone. The engines are
packages of their own:

| Package | Engine |
|---|---|
| [`omnisearch/typesense`](https://github.com/glitchr-omnisense/omnisearch-typesense) | [Typesense](https://typesense.org), over [`glitchr/typesense-bundle`](https://github.com/glitchr-studio/typesense-bundle)'s framework-free part: infix search, matched tokens, facets, discriminator sub-collections; in Symfony, that bundle hosts the indexes |
| `Omnisearch\Memory` (here) | the PHP process itself: for tests and first steps |

The shape comes from glitchr/typesense-bundle, whose DBAL request/response and ORM
manager/finder/mapping/transformer it generalises to any engine: what the bundle's YAML said of a
collection, an attribute says on the class.

## The layers

| | |
|---|---|
| `Mapping\` | `#[Index]`, `#[Field]`, `Type`; `AttributeLoader` (reflection), `ClassMetadata`, `MetadataRegistry` |
| `DBAL\` | `Driver` (what an engine does), `DriverFactory`, `Connection`, `Request`, `Filter`, `Response`, `Hit`, `Facet`, `Batch` (writes gathered, sent together) |
| `ORM\` | `Manager`, `Finder`, `Query`, `Result`, `Indexer` (the rule: what to index on persist, update, remove), `Transformer` (object to document), `PropertyReader`, `ObjectStore` (where the objects live) |
| `Registry` | the connections by name, from their configuration |
| `Bridge\Doctrine` | `DoctrineObjectStore` (entities identified, loaded back in the hits' order), `IndexListener` (the rule on Doctrine's events) |
| `Bridge\Symfony` | `OmnisearchBundle`, its commands, `Host` (an engine's own bundle hosting its indexes) |

Doctrine and Symfony are suggested, never required: nothing of either is loaded unless their
bridge is.

## Documentation

- [Overview: engines and layers](docs/index.md)
- [Installation, in plain PHP](docs/installation.md)
- [Symfony](docs/symfony.md) and [Laravel](docs/laravel.md)
- [Concepts: every class of the mapping, the DBAL and the ORM](docs/concepts.md)
- [Mapping: indexes and fields](docs/mapping.md)
- [Searching: queries, results, facets](docs/searching.md)
- [Indexing: the rule, Doctrine's events, failures](docs/indexing.md)
- [Configuration reference](docs/configuration.md)
- [Errors](docs/errors.md)
- [Testing, and the conformance suite](docs/testing.md)
- [The Docker harness](docs/harness.md)
- [Drivers: the contract, the memory engine, writing one](docs/drivers.md)
- [Verified, and not](docs/verified.md)

## Verified, and not

- Verified (`docker compose run --rm omnisearch test` in `docker/`, with Typesense 30.0): the whole
  layer on the memory engine; Doctrine entities indexed on flush and loaded back in the hits'
  order (SQLite); in plain PHP, nothing of Symfony nor Doctrine loaded on the memory engine and no
  class of a framework through omnisearch/typesense (`BareTest`); the Symfony bundle with the
  memory engine, and hosted by glitchr/typesense-bundle (omnisearch/typesense's `HostedTest`).
- Verified on an application: glitchr's lesringostars declares four of its five collections with
  these attributes, through omnisearch/typesense and hosted by glitchr/typesense-bundle: the
  schemas are those its server held, byte for byte, its 628 documents and its blog's searches
  (pages, terms, the tags facet) unchanged; the entities load in its Laravel twin with nothing of
  Symfony.
- The conformance suite (`Omnisearch\Testing\DriverContractTests`, [docs/drivers.md](docs/drivers.md#conformance-suite)):
  green on 2026-10-25 for the memory engine (offline) and omnisearch/typesense beside a Typesense
  30.0 container (`typesense/typesense:30.0.rca41`) - 48 tests in the workspace, none skipped.
- Not verified: an engine other than Typesense and memory; Doctrine's MongoDB ODM (the store
  falls back on `findBy()`, never run); Laravel (no bridge yet: one is written when an application
  under Laravel indexes).

## Licence

MIT. Omnisearch and its drivers are unofficial clients, affiliated with no search engine's
vendor.
