# omniscient/omnisearch

Search the way Doctrine is for SQL, **whatever the engine**: the classes declare their index by
attributes, a manager hands out finders, a query is written once, the objects come back from the
hits. A DBAL - connections, drivers, requests and responses - under an ORM - metadata, finders,
queries, results, the indexing rule.

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
| [`omnisearch/typesense`](https://github.com/glitchr-studio/omnisearch-typesense) | [Typesense](https://typesense.org), over [`glitchr/typesense-bundle`](https://github.com/glitchr-studio/typesense-bundle)'s framework-free part: infix search, matched tokens, facets, discriminator sub-collections; in Symfony, that bundle hosts the indexes |
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

- [Installation, in plain PHP](docs/installation.md)
- [Mapping: indexes and fields](docs/mapping.md)
- [Searching: queries, results, facets](docs/searching.md)
- [Drivers: the contract, the memory engine, writing one](docs/drivers.md)
- [Symfony](docs/symfony.md)
- [The Docker harness](docs/harness.md)

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
